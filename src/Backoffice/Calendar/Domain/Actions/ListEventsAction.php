<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\GoogleCalendar\Event;

class ListEventsAction
{
    /**
     * @return Collection<int, Event>
     */
    public function execute(): Collection
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return Event::get($startOfWeek, $endOfWeek);
    }
}
