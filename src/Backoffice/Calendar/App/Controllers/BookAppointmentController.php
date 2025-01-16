<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Backoffice\Calendar\App\Request\BookAppointmentFormRequest;
use Lightit\Backoffice\Calendar\App\Transformers\EventTransformer;
use Lightit\Backoffice\Calendar\Domain\Actions\BookAppointmentAction;

class BookAppointmentController
{
    public function __invoke(
        BookAppointmentFormRequest $request,
        BookAppointmentAction $bookAppointmentAction,
    ): JsonResponse {
        $event = $bookAppointmentAction->execute($request->toDto());

        return responder()
            ->success($event, EventTransformer::class)
            ->respond(JsonResponse::HTTP_CREATED);
    }
}
