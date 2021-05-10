<?php

use App\Http\Controllers\AllController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/request/create', [AllController::class, 'create_request']);
Route::post('/request/delete', [AllController::class, 'delete_request']);

Route::post('/response/create', [AllController::class, 'create_response']);
Route::post('/response/delete', [AllController::class, 'delete_response']);

Route::post('/user/create', [AllController::class, 'create']);
Route::post('/user/login', [AllController::class, 'login']);
Route::post('/user/forgot-password', [AllController::class, 'forgot_password']);
Route::post('/user/reset-password', [AllController::class, 'reset_password']);
Route::post('/user/delete', [AllController::class, 'delete_user']);

Route::post('/request/upvote', [AllController::class, 'upvote_request']);
Route::post('/response/upvote', [AllController::class, 'upvote_response']);
