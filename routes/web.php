<?php

use App\Services\FootballDataService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-api', function (FootballDataService $service) {
    return response()->json($service->getCompetitions());
});
