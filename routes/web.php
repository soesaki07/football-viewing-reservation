<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FavoriteTeamController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MypageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ShopController;
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
    Route::get('/shops/{shop}', [ShopController::class, 'show'])->name('shops.show');
    Route::get('/broadcasts/{broadcast}/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/broadcasts/{broadcast}/reservations/confirm', [ReservationController::class, 'confirm'])->name('reservations.confirm');
    Route::post('/broadcasts/{broadcast}/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/mypage', [MypageController::class, 'index'])->name('mypage');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservations/{reservation}/complete', [ReservationController::class, 'complete'])->name('reservations.complete');
    Route::get('/select/teams', [FavoriteTeamController::class, 'selectFavoriteTeams'])->name('select.teams');
    Route::put('/select/teams/save', [FavoriteTeamController::class, 'saveFavoriteTeams'])->name('save.teams');
});
