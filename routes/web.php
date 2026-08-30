<?php

use App\Services\FootballDataService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
if (app()->isLocal()) {
    Route::get('/test-api', function (FootballDataService $service) {
        return response()->json($service->getCompetitions());
    });
    Route::get('/teams-api', function (FootballDataService $service) {
        return response()->json($service->getTeams());
    });
}
