<?php

declare(strict_types=1);

namespace Lightit\Backoffice\ConversationItems\Domain\DataTransferObjects;

class ConversationItemDto
{
    public function __construct(
        public string $eventTime,
        public string $type,
        public string|null $text,
        public string $appURL,
        public string|null $authorDisplayName,
        public string $createdAt,
        public string $id,
        public string $conversationId,
        public string $direction,
    ) {
    }
}
