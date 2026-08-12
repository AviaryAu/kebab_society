<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withSchedule(function (Schedule $schedule): void {
        /*
         * Sources carry their own polling interval, so cron only has to ask
         * hourly which ones are due. withoutOverlapping matters more than
         * usual here: a slow crawl must never be joined by a second copy of
         * itself hitting the same publisher.
         */
        $schedule->command('ingest:due')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        /*
         * Copy is written on its own beat, so a dead model provider never stops
         * new listings arriving and an exhausted daily allowance simply resumes
         * tomorrow. Artwork follows the same reasoning.
         */
        $schedule->command('ingest:copy')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('ingest:images')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('ingest:prune')
            ->dailyAt('04:10')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
