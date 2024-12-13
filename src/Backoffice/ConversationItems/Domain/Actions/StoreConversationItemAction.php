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
            $this->replyMessageAutomatically($conversationItemDto->conversationId);
        }

        return $conversationItemDto;
    }

    private function replyMessageAutomatically(string $conversationId): void
    {
        $url = config('spruce.base_url') . 'conversations/' . $conversationId . '/messages';

        $payload = [
            'internal' => true,
            'body' => [
                [
                    'type' => 'text',
                    'value' => 'AI generated response',
                ],
            ],
        ];

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' . config('spruce.authorization_token'),
        ])->post($url, $payload);

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
