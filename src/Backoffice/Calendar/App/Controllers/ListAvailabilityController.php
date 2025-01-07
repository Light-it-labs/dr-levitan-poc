<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Controllers;

use Illuminate\Http\JsonResponse;
use Lightit\Backoffice\Calendar\App\Request\ListAvailabilityFormRequest;
use Lightit\Backoffice\Calendar\App\Transformers\SlotTransformer;
use Lightit\Backoffice\Calendar\Domain\Actions\ListAvailabilityAction;

class ListAvailabilityController
{
    public function __invoke(
        ListAvailabilityFormRequest $request,
        ListAvailabilityAction $listAvailabilityAction,
    ): JsonResponse {
        $events = $listAvailabilityAction->execute($request->toDto());

        return responder()
            ->success($events, SlotTransformer::class)
            ->respond(JsonResponse::HTTP_OK);
    }
}
