<?php

declare(strict_types=1);

namespace Lightit\Shared\App\Services;

use Illuminate\Support\Facades\Cache;
use Lightit\Backoffice\ConversationItems\Domain\DataTransferObjects\ConversationItemDto;

class MessagesCacheService
{
    public function storeConversationItem(ConversationItemDto $item)
    {
        $conversationId = $item->conversationId;
        $conversation = Cache::get("conversation_{$conversationId}", []);

        $conversation[] = $item;

        Cache::put("conversation_{$conversationId}", $conversation, now()->addDays(1));
    }

    /**
     * Retrieve a conversation by its ID.
     *
     * @return ConversationItemDto[] Array of ConversationItemDto objects
     */
    public function getConversation(string $conversationId): array
    {
        /** @var array<ConversationItemDto> $conversation */
        $conversation = Cache::get("conversation_{$conversationId}");

        return $conversation;
    }
}
