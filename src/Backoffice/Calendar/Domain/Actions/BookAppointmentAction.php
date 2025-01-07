<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\Domain\Actions;

use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\AvailabilityRequestDto;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\BookAppointmentDto;
use Lightit\Backoffice\Calendar\Domain\DataTransferObjects\SlotDto;
use Lightit\Shared\App\Exceptions\InvalidActionException;
use Spatie\GoogleCalendar\Event;

class BookAppointmentAction
{
    public function __construct(
        private readonly ListAvailabilityAction $listAvailabilityAction,
    ) {
    }

    public function execute(BookAppointmentDto $bookAppointmentDto): Event
    {
        $from = $bookAppointmentDto->startDateTime;
        $to = $bookAppointmentDto->startDateTime->copy()->addMinutes($bookAppointmentDto->duration);

        $availabilityRequest = new AvailabilityRequestDto($from->copy()->startOfDay(), $to->copy()->endOfDay());

        $availableSlots = $this->listAvailabilityAction->execute($availabilityRequest);

        $filteredSlots = $availableSlots
            ->filter(function (SlotDto $slot) use ($from, $to) {
                return $slot->start <= $from && $slot->end >= $to;
            });

        if (count($filteredSlots) == 0) {
            throw new InvalidActionException(
                'There are no available slots at the specified time'
            );
        }

        $event = new Event();

        $event->__set('name', $bookAppointmentDto->title);
        $event->__set('startDateTime', $from);
        $event->__set('endDateTime', $to);

        $event->save();

        return $event;
    }
}
