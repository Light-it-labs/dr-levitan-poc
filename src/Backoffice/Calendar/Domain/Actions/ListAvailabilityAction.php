<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\AvailabilityRequestDto;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\SlotDto;
use Spatie\GoogleCalendar\Event;

class ListAvailabilityAction
{
    /**
     * @return Collection<int, SlotDto>
     */
    public function execute(AvailabilityRequestDto $availabilityRequestDto): Collection
    {
        $fromDate = Carbon::parse($availabilityRequestDto->fromDate)->startOfDay();
        $toDate = Carbon::parse($availabilityRequestDto->toDate)->endOfDay();

        /** @var Collection<int, Event> $allSlots */
        $allSlots = Event::get(startDateTime: $fromDate, endDateTime: $toDate);

        return $this->getAvailableSlots($allSlots);
    }

    /**
     * Get available slots.
     *
     * @param Collection<int, Event> $allSlots
     *
     * @return Collection<int, SlotDto>
     */
    private function getAvailableSlots(Collection $allSlots): Collection
    {
        $availableSlots = $allSlots->filter(fn (Event $event): bool => $event->__get('name') === 'Available');

        /**
         * @var Collection<int, SlotDto> $occupiedSlots
         */
        $occupiedSlots = $allSlots->filter(fn (Event $event) => $event->__get('name') !== 'Available')
            ->map(fn (Event $event) => new SlotDto(
                $event->__get('startDateTime'),
                $event->__get('endDateTime')
            ))
            ->sortBy('start');

        /**
         * @var Collection<int, SlotDto> $freeSlots
         */
        $freeSlots = $availableSlots->flatMap(function (Event $availableSlot) use ($occupiedSlots) {
            $availableStart = $availableSlot->__get('startDateTime');
            $availableEnd = $availableSlot->__get('endDateTime');

            $ranges = collect([new SlotDto($availableStart, $availableEnd)]);

            foreach ($occupiedSlots as $occupied) {
                $ranges = $ranges->flatMap(function ($range) use ($occupied) {
                    $start = $range->start;
                    $end = $range->end;

                    if ($occupied->end <= $start || $occupied->start >= $end) {
                        return [$range];
                    }

                    $result = [];
                    if ($occupied->start > $start) {
                        $result[] = new SlotDto($start, $occupied->start);
                    }
                    if ($occupied->end < $end) {
                        $result[] = new SlotDto($occupied->end, $end);
                    }

                    return $result;
                });
            }

            return $ranges;
        });

        return $freeSlots->filter(fn ($range) => $range->start < $range->end);
    }
}
