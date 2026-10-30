<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/dashboard/{importId?}', [DashboardController::class, 'show'])->name('dashboard');

Route::get('/exports/{importId}/csv', [ExportController::class, 'csv'])->name('exports.csv');
Route::get('/exports/{importId}/json', [ExportController::class, 'json'])->name('exports.json');
Route::get('/exports/{importId}/pdf', [ExportController::class, 'pdf'])->name('exports.pdf');
Route::post('/exports/{importId}/png', [ExportController::class, 'png'])->name('exports.png');
