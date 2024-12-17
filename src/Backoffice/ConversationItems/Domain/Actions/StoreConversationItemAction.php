<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\Domain\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lightit\Backoffice\ConversationItems\Domain\DataTransferObjects\ConversationItemDto;

class StoreConversationItemAction
{
    public function execute(ConversationItemDto $conversationItemDto): ConversationItemDto
    {
        if ($conversationItemDto->direction === 'inbound') {
            $taskId = $this->createTask($conversationItemDto);

            $this->replyMessageAutomatically($conversationItemDto->conversationId, $taskId);
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

    private function replyMessageAutomatically(string $conversationId, string|null $taskId): void
    {
        $message = 'This will be an AI-generated response.';

        if ($taskId) {
            $taskUrl = config('clickup.app_url') . $taskId;
            $message .= "\nA task has been generated linked to this conversation. To view it, click here: $taskUrl";
        }

        $payload = [
            'internal' => true,
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
}
