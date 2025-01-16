<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lightit\Backoffice\Calendar\App\Controllers\BookAppointmentController;
use Lightit\Backoffice\Calendar\App\Controllers\ListAvailabilityController;
use Lightit\Backoffice\ConversationItems\App\Controllers\StoreConversationItemController;
use Lightit\Backoffice\Users\App\Controllers\DeleteUserController;
use Lightit\Backoffice\Users\App\Controllers\GetUserController;
use Lightit\Backoffice\Users\App\Controllers\ListUserController;
use Lightit\Backoffice\Users\App\Controllers\StoreUserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Users Routes
|--------------------------------------------------------------------------
*/
Route::prefix('users')
    ->group(static function () {
        Route::get('/', ListUserController::class);
        Route::get('/{user}', GetUserController::class)->withTrashed();
        Route::post('/', StoreUserController::class);
        Route::delete('/{user}', DeleteUserController::class);
    });

/*
|--------------------------------------------------------------------------
| Webhooks Routes
|--------------------------------------------------------------------------
*/
Route::prefix('webhooks')
    ->group(static function () {
        Route::post('/conversation-item', StoreConversationItemController::class);
    });

/*
|--------------------------------------------------------------------------
| Google Calendar Routes
|--------------------------------------------------------------------------
*/
Route::prefix('calendar')
    ->group(static function () {
        Route::get('/availability', ListAvailabilityController::class);
        Route::post('/book-appointment', BookAppointmentController::class);
    });

