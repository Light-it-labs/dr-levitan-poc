<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Lightit\Backoffice\ConversationItems\App\Request\StoreConversationItemRequest;
use Lightit\Backoffice\ConversationItems\Domain\Actions\StoreConversationItemAction;

class StoreConversationItemController
{
    public function __invoke(
        StoreConversationItemRequest $conversationItemRequest,
        StoreConversationItemAction $storeConversationItemAction,
    ): JsonResponse {
        $conversationItem = $storeConversationItemAction->execute($conversationItemRequest->toDto());

        Log::info('Conversation item created', ['conversation_item' => $conversationItem]);

        return responder()
            ->success()
            ->respond(JsonResponse::HTTP_CREATED);
    }
}
