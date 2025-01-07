<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\DataTransferObjects;

use Carbon\Carbon;

class SlotDto
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {
    }
}
