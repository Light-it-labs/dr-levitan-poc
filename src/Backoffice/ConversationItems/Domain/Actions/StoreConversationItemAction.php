<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\Domain\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lightit\Backoffice\ConversationItems\Domain\DataTransferObjects\ConversationItemDto;
use Lightit\Shared\App\Services\MessagesCacheService;
use OpenAI;

class StoreConversationItemAction
{
    public function __construct(
        private readonly MessagesCacheService $messagesCacheService,
    ) {
    }

    public function execute(ConversationItemDto $conversationItemDto): ConversationItemDto
    {
        $this->messagesCacheService->storeConversationItem($conversationItemDto);

        if ($conversationItemDto->direction === 'inbound') {
            $conversationHistory = $this->messagesCacheService->getConversation($conversationItemDto->conversationId);

            $prompt = $this->generatePrompt($conversationHistory, $conversationItemDto->text);

            Log::info('Prompt', ['promptContent' => $prompt]);

            $aiResponse = $this->generateAIResponse($prompt);

            $taskId = null;

            if ($aiResponse['task'] !== null) {
                $task = $aiResponse['task'];
                $taskId = $this->createTask(
                    $conversationItemDto,
                    $task['title'],
                    $task['description'],
                    $task['category']
                );
                if ($taskId) {
                    $this->postInternalMessage($taskId, $conversationItemDto->conversationId);
                }
            }

            Log::info('AI', ['response' => $aiResponse]);

            $this->replyMessageAutomatically($conversationItemDto->conversationId, $aiResponse['message']);
        }

        return $conversationItemDto;
    }

    private function createTask(
        ConversationItemDto $conversationItemDto,
        string $title,
        string $description,
        string $category,
    ): string|null {
        $url = config('clickup.base_url') . 'list/' . config('clickup.list_id') . '/task';

        $payload = [
            'name' => $title . ' ' . $conversationItemDto->authorDisplayName,
            'description' => "Message from: {$conversationItemDto->authorDisplayName}.\n" .
                "Summary: {$description}.\n" .
                "To view the complete conversation, click here: {$conversationItemDto->appURL}",
            'priority' => 3,
            'tags' => [$category],
        ];

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => config('clickup.authorization_token'),
        ])->post($url, $payload);

        if ($response->successful()) {
            /** @var array{id: string} $responseData */
            $responseData = $response->json();
            $taskId = $responseData['id'];

            Log::info('Task created successfully in ClickUp', ['task_id' => $taskId, 'response' => $responseData]);

            return $taskId;
        } else {
            Log::error('Error creating task in Click Up', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
        }

        return null;
    }

    private function postInternalMessage(string $taskId, string $conversationId): void
    {
        $taskUrl = config('clickup.app_url') . $taskId;
        $payload = [
            'internal' => true,
            'body' => [
                [
                    'type' => 'text',
                    'value' => "\nA task has been generated linked to this conversation. To view it, click here: $taskUrl",
                ],
            ],
        ];

        $spruceUrl = config('spruce.base_url') . 'conversations/' . $conversationId . '/messages';

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' . config('spruce.authorization_token'),
        ])->post($spruceUrl, $payload);

        if ($response->successful()) {
            Log::info('Internal note sent to Spruce successfully', ['response' => $response->json()]);
        } else {
            Log::error('Error sending internal note to Spruce', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
        }
    }

    private function replyMessageAutomatically(string $conversationId, string $message): void
    {
        $payload = [
            'internal' => false,
            'body' => [
                [
                    'type' => 'text',
                    'value' => $message,
                ],
            ],
        ];

        $spruceUrl = config('spruce.base_url') . 'conversations/' . $conversationId . '/messages';

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' . config('spruce.authorization_token'),
        ])->post($spruceUrl, $payload);

        if ($response->successful()) {
            Log::info('Message sent to Spruce successfully', ['response' => $response->json()]);
        } else {
            Log::error('Error sending message to Spruce', [
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
        }
    }

    /**
     * Generate a prompt array for OpenAI based on conversation history and a new message.
     *
     * @param ConversationItemDto[] $conversationHistory Array of conversation history items
     * @param string                $newMessage          The new message from the user
     *
     * @return array The formatted array of messages for OpenAI
     */
    private function generatePrompt(array $conversationHistory, string $newMessage): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a virtual medical assistant. Respond professionally and empathetically. '
                    . 'When a user requests or changes an appointment, provide three randomly available time slots within business hours (9:00 AM to 5:00 PM) for them to choose from. '
                    . 'Ensure the time slots are clearly presented, and allow the user to select one. After the selection, confirm that the appointment will be scheduled. '
                    . 'For task-related requests, keep the message brief and simply mention that the issue will be referred to the doctor for follow-up.'
                    . ' Tasks should be returned only in the following cases:'
                    . ' - When the patient asks a medical question, return a task with the category "Talk to Victor".'
                    . ' - When a user expresses interest in enrolling for a service, return a task with the category "Enrollment".'
                    . ' - When a user confirms an appointment time, return a task with the category "Send confirmation".'
                    . ' For any other type of inquiry, the response to the patient should be returned in the message field, and the task field should be returned as null.',
            ],
        ];

        foreach ($conversationHistory as $item) {
            $role = $item->direction === 'inbound' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => $item->text,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];

        return $messages;
    }

    /**
     * Generates an AI response based on the given prompt.
     *
     * @param array $prompt The prompt for the AI.
     *
     * @return array{message: string, task: array|null} The structured response from the AI.
     */
    private function generateAIResponse(array $prompt): array
    {
        $apiKey = config('openai.api_key');

        if (! is_string($apiKey) || empty($apiKey)) {
            throw new InvalidArgumentException('OpenAI API key is not configured.');
        }
        $client = OpenAI::client($apiKey);

        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $prompt,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'medical_assistant_response',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'description' => 'The message content to be sent to the user.',
                            ],
                            'task' => [
                                'type' => 'object',
                                'description' => 'An object representing the task that may need to be created. If no task is needed, this field will be null.',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'category' => ['type' => 'string'],
                                ],
                                'required' => ['title', 'description', 'category'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['message', 'task'],
                        'additionalProperties' => false,
                    ],
                    'strict' => true,
                ],
            ],
        ]);

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            return [
                'message' => 'An error occurred while generating the response',
                'task' => null,
            ];
        }

        /**
         * @var array{
         *     message: string,
         *     task: array|null
         * } $decodedResponse
         */
        $decodedResponse = json_decode($content, true);

        return $decodedResponse;
    }
}
