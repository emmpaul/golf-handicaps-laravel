<?php

namespace App\Providers;

use App\Http\Session\AnonymousDatabaseSessionHandler;
use App\Support\WorkerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSessionStorage();
        $this->configureWorkerHeartbeat();
    }

    /**
     * Write the worker heartbeat on each loop of queue:work and at the end of each job.
     */
    protected function configureWorkerHeartbeat(): void
    {
        Queue::looping(fn () => WorkerHeartbeat::beat());
        Event::listen(JobProcessed::class, fn () => WorkerHeartbeat::beat());
        Event::listen(JobFailed::class, fn () => WorkerHeartbeat::beat());
    }

    /**
     * Store database sessions without linking them to a person.
     */
    protected function configureSessionStorage(): void
    {
        Session::extend('database', fn (Application $app): AnonymousDatabaseSessionHandler => new AnonymousDatabaseSessionHandler(
            $app->make('db')->connection(config('session.connection')),
            config('session.table'),
            config('session.lifetime'),
            $app,
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
