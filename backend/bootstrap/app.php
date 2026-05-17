<?php

use App\Domain\Narrative\Exceptions\InvalidLlmConfigException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\NoDataException;
use App\Exceptions\ServiceUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ServiceUnavailableException $e, Request $request): ?JsonResponse {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'       => $e->getMessage(),
                    'service'     => $e->service,
                    'retry_after' => 300,
                ], 503);
            }

            return null;
        });

        $exceptions->render(function (InvalidTokenException $e, Request $request): ?JsonResponse {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error'        => $e->getMessage(),
                    'service'      => $e->service,
                    'settings_url' => '/settings',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (NoDataException $e, Request $request): ?JsonResponse {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (InvalidLlmConfigException $e, Request $request): ?JsonResponse {
            if ($request->wantsJson() || $request->is('api/*')) {
                Log::warning('Report generation aborted: invalid LLM config', [
                    'violations' => $e->violations,
                ]);

                return response()->json([
                    'error'        => 'LLM configuration is invalid',
                    'violations'   => $e->violations,
                    'settings_url' => '/settings',
                ], 422);
            }

            return null;
        });
    })->create();
