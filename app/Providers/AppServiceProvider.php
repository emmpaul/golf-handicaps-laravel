<?php

namespace App\Providers;

use App\Http\Session\AnonymousDatabaseSessionHandler;
use App\Support\WorkerHeartbeat;
use Carbon\CarbonImmutable;
use DateTimeZone;
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
use InvalidArgumentException;

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
        $this->ensureAiConfiguration();
        $this->ensureSgtConfiguration();
        $this->configureSessionStorage();
        $this->configureWorkerHeartbeat();
    }

    /**
     * Stop every process, web, worker and migrate alike, when the AI budget cannot be counted:
     * an unknown time zone, or a model without a price in config/golf.php, with or without an
     * API key (design D4).
     *
     * @throws InvalidArgumentException naming the variable to fix
     */
    protected function ensureAiConfiguration(): void
    {
        $timeZone = config()->string('golf.ai.budget_time_zone');
        // ALL_WITH_BC: America/Montreal, the default, is a backward-compatible link, absent from
        // the canonical list.
        if (! in_array($timeZone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException("AI_BUDGET_TIME_ZONE {$timeZone} n’est pas un fuseau horaire IANA connu");
        }

        $model = config()->string('golf.ai.model');
        if (! array_key_exists($model, config()->array('golf.ai.prices'))) {
            throw new InvalidArgumentException("AI_MODEL {$model} n’a pas de tarif dans config/golf.php");
        }
    }

    /**
     * Stop every process, web, worker and migrate alike, when SGT_LOOKUP is neither on nor off
     * (course-rating-sgt, design D10).
     *
     * @throws InvalidArgumentException naming the variable to fix
     */
    protected function ensureSgtConfiguration(): void
    {
        $lookup = config()->string('golf.sgt.lookup');
        if (! in_array($lookup, ['on', 'off'], true)) {
            throw new InvalidArgumentException("SGT_LOOKUP {$lookup} doit valoir on ou off");
        }
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
