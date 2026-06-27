<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\TechnicianInviteController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::get('/health', [
    HealthController::class,
    'index',
]);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post(
    '/technician-invites/accept',
    [TechnicianInviteController::class, 'accept']
);

Route::middleware([
    'auth:api',
    'active',
    'token.version',
])->group(function () {

    Route::get(
        '/vehicles',
        [VehicleController::class, 'index']
    );

    Route::get(
        '/vehicles/{id}',
        [VehicleController::class, 'show']
    );

    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);

    Route::post(
        '/vehicles',
        [VehicleController::class, 'store']
    );

    Route::get(
        '/vehicles/plate/{plateNumber}',
        [VehicleController::class, 'searchByPlate']
    );

    Route::post(
        '/vehicle/plate/history',
        [VehicleController::class, 'addNewHistory']
    );

    Route::get(
        '/vehicles/dashboard/stats',
        [VehicleController::class, 'dashboardStats']
    );

    Route::post('/technician-invites', [
        TechnicianInviteController::class,
        'store',
    ]);

    Route::get(
        '/devices',
        [TechnicianInviteController::class, 'devices']
    );

    Route::get('/me', [
        AuthController::class,
        'me',
    ]);

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);
});
