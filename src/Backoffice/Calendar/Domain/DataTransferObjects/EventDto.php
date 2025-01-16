<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\DataTransferObjects;

class EventDto
{
    public function __construct(
        public string $name,
        public string $startDateTime,
        public string $endDateTime,
    ) {
    }
}
