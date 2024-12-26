<?php

declare(strict_types=1);

namespace Lightit\Backoffice\Calendar\App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lightit\Backoffice\Calendar\App\Transformers\EventTransformer;
use Lightit\Backoffice\Calendar\Domain\Actions\ListEventsAction;

class ListEventsController
{
    public function __invoke(
        Request $request,
        ListEventsAction $listEventsAction,
    ): JsonResponse {
        $events = $listEventsAction->execute();

        return responder()
            ->success($events, EventTransformer::class)
            ->respond(JsonResponse::HTTP_CREATED);
    }
}
