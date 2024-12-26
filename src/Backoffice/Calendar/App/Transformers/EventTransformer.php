<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Transformers;

use Flugg\Responder\Transformers\Transformer;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\EventDto;

class EventTransformer extends Transformer
{
    public function transform(EventDto $event): array
    {
        return [
            // @phpstan-ignore-next-line
            'name' => $event->name,
            // @phpstan-ignore-next-line
            'start' => $event->startDateTime,
            // @phpstan-ignore-next-line
            'end' => $event->endDateTime,
        ];
    }
}
