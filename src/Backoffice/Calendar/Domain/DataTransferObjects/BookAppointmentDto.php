<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\DataTransferObjects;

use Carbon\Carbon;

class BookAppointmentDto
{
    public function __construct(
        public string $title,
        public Carbon $startDateTime,
        public int $duration,
    ) {
    }
}
