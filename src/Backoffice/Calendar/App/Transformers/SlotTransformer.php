<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Transformers;

use Flugg\Responder\Transformers\Transformer;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\SlotDto;

class SlotTransformer extends Transformer
{
    public function transform(SlotDto $event): array
    {
        return [
            'start' => $event->start,
            'end' => $event->end,
        ];
    }
}
