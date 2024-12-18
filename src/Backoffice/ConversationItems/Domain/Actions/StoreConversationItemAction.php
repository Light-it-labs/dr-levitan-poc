<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\Domain\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $taskId = $this->createTask($conversationItemDto);

            $conversationHistory = $this->messagesCacheService->getConversation($conversationItemDto->conversationId);

            $prompt = $this->generatePrompt($conversationHistory, $conversationItemDto->text);

            $aiResponse = $this->generateAIResponse($conversationItemDto->text);

            $this->replyMessageAutomatically($conversationItemDto->conversationId, $aiResponse, null);
        }

        return $conversationItemDto;
    }

    private function createTask(ConversationItemDto $conversationItemDto): string|null
    {
        $url = config('clickup.base_url') . 'list/' . config('clickup.list_id') . '/task';

        $payload = [
            'name' => 'Task created via API - ' . uniqid(),
            'description' => "Message from: {$conversationItemDto->authorDisplayName}.\n" .
                "Content: {$conversationItemDto->text}.\n" .
                "To view the complete conversation, click here: {$conversationItemDto->appURL}",
            'priority' => 3,
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

    private function replyMessageAutomatically(string $conversationId, string $message, string|null $taskId): void
    {
        if ($taskId) {
            $taskUrl = config('clickup.app_url') . $taskId;
            $message .= "\nA task has been generated linked to this conversation. To view it, click here: $taskUrl";
        }

        $payload = [
            // 'internal' => true,
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
                    . 'If the user requests an appointment, provide three random available time slots for them to choose from, '
                    . 'ensuring the times are within business hours (e.g., 9:00 AM to 5:00 PM).',
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

    private function generateAIResponse(string $message): string
    {
        $client = OpenAI::client(config('openai.api_key'));

        $response = $client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a virtual medical assistant. Respond in a professional and empathetic manner.  the user requests an appointment, provide three random available time slots for them to choose from, ensuring the times are within business hours (e.g., 9:00 AM to 5:00 PM).'],
                ['role' => 'user', 'content' => $message],
            ],
        ]);

        return $response['choices'][0]['message']['content'] ?? 'Sorry, I cannot respond at the moment.';
    }
}
