<?php

namespace App\Providers;

use App\Http\Session\AnonymousDatabaseSessionHandler;
use App\Integrations\Sentry\RetriedJobExceptions;
use App\Support\QueryClock;
use App\Support\WorkerHeartbeat;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Sentry\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(QueryClock::class);
        $this->app->singleton(RetriedJobExceptions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->ensureAiConfiguration();
        $this->ensureSgtConfiguration();
        $this->ensureSentryConfiguration();
        $this->configureSessionStorage();
        $this->configureWorkerHeartbeat();
        $this->configureRetriedJobExceptions();
        $this->configureQueryClock();
    }

    /**
     * Add the time of each SQL query to the clock of the current request, read by
     * AddServerTiming (design D1). Registered once at boot; app() resolves the clock of the
     * request running the query.
     */
    protected function configureQueryClock(): void
    {
        DB::listen(fn (QueryExecuted $query) => app(QueryClock::class)->record($query->time ?? 0.0));
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
     * Stop every process, web, worker and migrate alike, when SENTRY_LARAVEL_DSN is set and
     * malformed (design D7 of error-alerts): left alone, the SDK drops the value and sends nothing
     * without a word. An empty or absent value means no alerts. The message leaves the value out,
     * as it can hold a key and goes to the container logs.
     *
     * @throws InvalidArgumentException naming the variable to fix
     */
    protected function ensureSentryConfiguration(): void
    {
        $dsn = config('sentry.dsn');
        if ($dsn === null || $dsn === '') {
            return;
        }

        try {
            Dsn::createFromString((string) $dsn);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('SENTRY_LARAVEL_DSN n’est pas un DSN Sentry valide (attendu : https://<clé>@<hôte>/<projet>)');
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
     * Hold the exception of a job released for a retry: the worker reports it just after this
     * event, and SentryEventFilter drops it (design D4 of error-alerts). The log keeps it.
     */
    protected function configureRetriedJobExceptions(): void
    {
        Event::listen(JobReleasedAfterException::class, function (JobReleasedAfterException $event): void {
            if ($event->exception !== null) {
                app(RetriedJobExceptions::class)->remember($event->exception);
            }
        });
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
