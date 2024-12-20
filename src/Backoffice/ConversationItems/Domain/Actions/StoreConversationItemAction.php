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
                'content' => 'You are a virtual medical assistant. Respond in a professional and empathetic manner. '
                    . 'If the user requests an appointment or changes an existing appointment, you must provide three random available time slots for them to choose from. '
                    . 'These time slots should be within business hours (e.g., 9:00 AM to 5:00 PM) and clearly presented. '
                    . 'Make sure to give the user the ability to select one of these options. After they select a time, let them know the appointment will be scheduled. '
                    . 'If the user provides a preference (e.g., morning or afternoon), try to accommodate that preference in the available options. '
                    . 'When responding, always return only one JSON object with the following fields:'
                    . ' 1. A "message" field with the content that will be sent back to the user.'
                    . ' 2. A "task" field (which is an object) that must be created in case of clinical inquiries or task-related requests.'
                    . ' The "task" should contain a title, description, and category. For clinical inquiries, use the category "Talk to Victor" to indicate that it needs doctor review.'
                    . ' In cases where a task is returned, do not provide too much information, but simply indicate that it will be referred to the doctor for follow-up.'
                    . ' If the user’s request does not require a task (i.e., non-clinical, appointment requests, or non-task related), the "task" should be null.'
                    . ' The response must contain ONLY this JSON object with no additional text or explanations.'
                    . ' IMPORTANT: The "task" should never be embedded inside the "message" field, nor should it be included as {"task": null} inside the message. The "task" must always be separate from the "message".'
                    . ' Example 1 (when no task is required, e.g., appointment scheduling):'
                    . ' { "message": "Here are three available time slots for your lab appointment: 1. Monday, 9:00 AM 2. Monday, 1:00 PM 3. Tuesday, 11:00 AM. Please let me know which slot works for you.", "task": null }'
                    . ' Example 2 (when a task is required, e.g., clinical inquiry):'
                    . ' { "message": "Thank you for your inquiry. Your request is being reviewed by our team and we will get back to you shortly.", "task": { "title": "Review patient symptoms", "description": "Patient reports symptoms that need doctor review", "category": "Talk to Victor" } }'
                    . ' Do not include any additional text, explanations, or any other information outside of the JSON object.',
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
            'model' => 'gpt-4',
            'messages' => $prompt,
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
