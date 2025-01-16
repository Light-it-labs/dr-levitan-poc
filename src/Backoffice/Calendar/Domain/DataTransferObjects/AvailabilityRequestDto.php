<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\DataTransferObjects;

use Carbon\Carbon;

class AvailabilityRequestDto
{
    public function __construct(
        public Carbon $fromDate,
        public Carbon $toDate,
    ) {
    }
}
