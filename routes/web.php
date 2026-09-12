<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FavoriteTeamController;
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
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
    Route::get('/matches/{id}', [MatchController::class, 'show'])->name('matches.show');
    Route::get('/select/teams', [FavoriteTeamController::class, 'selectFavoriteTeams'])->name('select.teams');
    Route::put('/select/teams/save', [FavoriteTeamController::class, 'saveFavoriteTeams'])->name('save.teams');
});
