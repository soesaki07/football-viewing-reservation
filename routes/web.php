<?php

use App\Http\Controllers\MatchController;
use App\Services\FootballDataService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('matches.index');
});
if (app()->isLocal()) {
    Route::get('/test-api', function (FootballDataService $service) {
        return response()->json($service->getCompetitions());
    });
    Route::get('/teams-api', function (FootballDataService $service) {
        return response()->json($service->getTeams());
    });
    Route::get('/matches-api', function (FootballDataService $service) {
        return response()->json($service->getMatches());
    });
}
Route::get('/matches/index', [MatchController::class, 'index'])->name('matches.index');
Route::get('/matches/{id}', [MatchController::class, 'show'])->name('matches.show');
