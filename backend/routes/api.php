<?php

declare(strict_types=1);

use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProjectMappingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Sync
Route::get('/sync/status', [SyncController::class, 'status'])->name('sync.status');
Route::post('/sync/trigger', [SyncController::class, 'trigger'])->name('sync.trigger');
Route::post('/sync/resync', [SyncController::class, 'resync'])->name('sync.resync');
Route::get('/sync/stream', [SyncController::class, 'stream'])->name('sync.stream');

// Inbox
Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
Route::post('/inbox/assign', [InboxController::class, 'assign'])->name('inbox.assign');
Route::post('/inbox/bulk-assign', [InboxController::class, 'bulkAssign'])->name('inbox.bulk-assign');
Route::post('/inbox/ignore', [InboxController::class, 'ignore'])->name('inbox.ignore');
Route::post('/inbox/create-task', [InboxController::class, 'createTask'])->name('inbox.create-task');

// Project Mappings
Route::get('/projects/mappings', [ProjectMappingController::class, 'index'])->name('mappings.index');
Route::post('/projects/mappings', [ProjectMappingController::class, 'store'])->name('mappings.store');
Route::put('/projects/mappings/{mapping}', [ProjectMappingController::class, 'update'])->name('mappings.update');
Route::delete('/projects/mappings/{mapping}', [ProjectMappingController::class, 'destroy'])->name('mappings.destroy');

// Reports
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
Route::get('/reports/{report}/preview', [ReportController::class, 'preview'])->name('reports.preview');
Route::put('/reports/{report}/day/{date}', [ReportController::class, 'updateDay'])->name('reports.day.update');
Route::put('/reports/{report}/task/{taskId}', [ReportController::class, 'updateTask'])->name('reports.task.update');
Route::post('/reports/{report}/task/{taskId}/regenerate', [ReportController::class, 'regenerateTask'])->name('reports.task.regenerate');
Route::post('/reports/{report}/day/{date}/regenerate', [ReportController::class, 'regenerateDay'])->name('reports.day.regenerate');
Route::post('/reports/{report}/task/{taskId}/undo', [ReportController::class, 'undoTask'])->name('reports.task.undo');
Route::post('/reports/{report}/day/{date}/undo', [ReportController::class, 'undoDay'])->name('reports.day.undo');
Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
Route::get('/reports/{report}/export-prompt', [ReportController::class, 'exportPrompt'])->name('reports.export-prompt');

// Settings
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

// Health Check
Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');
