<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EmailAccountController;
use App\Http\Controllers\API\EmailController;
use App\Http\Controllers\API\ExportController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\SystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/accept-invite', [AuthController::class, 'acceptInvite']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/invite', [AuthController::class, 'invite']);

        Route::get('email-accounts', [EmailAccountController::class, 'index']);
        Route::post('email-accounts', [EmailAccountController::class, 'store']);
        Route::patch('email-accounts/{emailAccount}', [EmailAccountController::class, 'update']);
        Route::post('email-accounts/{emailAccount}/test', [EmailAccountController::class, 'test']);

        Route::get('emails', [EmailController::class, 'index']);
        Route::get('emails/{email}', [EmailController::class, 'show']);
        Route::post('emails/{email}/fetch-body', [EmailController::class, 'fetchBody']);
        Route::post('emails/{email}/reply', [EmailController::class, 'reply']);
        Route::post('emails/{email}/forward', [EmailController::class, 'forward']);

        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::post('projects/from-email/{email}', [ProjectController::class, 'createFromEmail']);
        Route::post('projects/{project}/emails', [ProjectController::class, 'linkEmail']);

        Route::get('contacts', [ContactController::class, 'index']);
        Route::get('contacts/{contact}', [ContactController::class, 'show']);
        Route::patch('contacts/{contact}', [ContactController::class, 'update']);

        Route::get('dashboards/summary', [DashboardController::class, 'summary']);

        Route::post('exports/projects', [ExportController::class, 'queueProjects']);
        Route::post('exports/contacts', [ExportController::class, 'queueContacts']);
        Route::get('exports/{export}', [ExportController::class, 'show']);

        Route::get('system/health', [SystemController::class, 'health']);
        Route::get('sync/logs', [SystemController::class, 'syncLogs']);
    });

    Route::post('system/cron-run', [SystemController::class, 'cronRun'])
        ->middleware('cron.signature');
});
