<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Testing\Fakes\FakeClient;
use Laravel\Octane\Worker;

/**
 * Spec deployment « Aucun état d'une requête dans la suivante » (design D4): every request of a
 * test goes through one Octane worker, as in production under FrankenPHP. The worker boots the
 * application once, clones it for each request, runs the RequestReceived listeners of
 * config/octane.php and resets the clone afterwards. Each browser keeps its own cookies.
 *
 * Mutations that turn these tests red:
 * - Passcode::opened() keeping an opened session in a static property
 *   (self::$opened = self::$opened || …): B gets /rounds without a cookie (200), and the
 *   session opened with the former passcode stays open. Memoizing the first result (??=)
 *   turns all four red, A being kept out after its own first request.
 * - FlushSessionState removed from the RequestReceived listeners of config/octane.php: B gets
 *   /rounds without a cookie, and reads the flash message and the validation errors left in
 *   the session store by A.
 */
final class OctaneWorkerUnderTest
{
    private FakeClient $client;

    private Worker $worker;

    public function __construct(Application $app)
    {
        $this->client = new FakeClient([]);
        $this->worker = new Worker(new class($app) extends ApplicationFactory
        {
            public function __construct(private Application $app)
            {
                parent::__construct($app->basePath());
            }

            /**
             * The application of the test, already booted, warmed as Octane warms its own.
             *
             * @param  array<string, mixed>  $initialInstances
             */
            public function createApplication(array $initialInstances = []): Application
            {
                foreach ($initialInstances as $key => $value) {
                    $this->app->instance($key, $value);
                }

                return $this->warm($this->app);
            }
        }, $this->client);

        $this->worker->boot([Client::class => $this->client]);
    }

    /**
     * Serve one request in the worker and return its response.
     *
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>  $parameters
     */
    public function serve(string $method, string $uri, array $cookies, array $parameters = [], ?string $referer = null): TestResponse
    {
        $server = $referer === null ? [] : ['HTTP_REFERER' => "http://localhost{$referer}"];
        $request = Request::create("http://localhost{$uri}", $method, $parameters, $cookies, [], $server);

        $before = count($this->client->responses);
        $this->worker->handle($request, new RequestContext(['request' => $request]));

        expect($this->client->errors)->toBe([])
            ->and($this->client->responses)->toHaveCount($before + 1);

        return TestResponse::fromBaseResponse(end($this->client->responses));
    }
}

/**
 * A browser of the test: it sends its own cookies only, and keeps those the worker sets.
 */
final class BrowserOfWorker
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(private OctaneWorkerUnderTest $worker) {}

    public function get(string $uri): TestResponse
    {
        return $this->send('GET', $uri);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function post(string $uri, array $parameters, ?string $from = null): TestResponse
    {
        return $this->send('POST', $uri, $parameters, $from);
    }

    public function logIn(): void
    {
        $this->post('/login', ['passcode' => 'birdie-2026'])->assertRedirect('/');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function send(string $method, string $uri, array $parameters = [], ?string $from = null): TestResponse
    {
        $response = $this->worker->serve($method, $uri, $this->cookies, $parameters, $from);

        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }
}

beforeEach(function () {
    // The production session driver, through AnonymousDatabaseSessionHandler.
    config(['session.driver' => 'database']);

    $worker = new OctaneWorkerUnderTest($this->app);
    $this->browserA = new BrowserOfWorker($worker);
    $this->browserB = new BrowserOfWorker($worker);
});

test('a browser without a cookie is sent to /login right after a request of an open session', function () {
    $this->browserA->logIn();
    $this->browserA->get('/rounds')->assertOk();

    $this->browserB->get('/rounds')->assertRedirect('/login');
});

test('the confirmation of a round saved by one browser is not shown to another', function () {
    createPlayersOnTest('Gab', 'Manu');
    $this->browserB->logIn();
    $this->browserA->logIn();

    $this->browserA->post('/rounds', referenceCardData($this->gab->id, $this->manu->id), '/rounds/new')
        ->assertRedirect('/rounds');

    $pageOfB = $this->browserB->get('/rounds')->assertOk()->inertiaPage();
    expect($pageOfB['component'])->toBe('rounds/index')
        ->and($pageOfB['flash'])->toBe([]);

    expect($this->browserA->get('/rounds')->assertOk()->inertiaPage()['flash'])
        ->toBe(['toast' => ['type' => 'success', 'message' => 'Round enregistré.']]);
});

test('the validation errors of one browser are not on the page of another', function () {
    $this->browserB->logIn();
    $this->browserA->logIn();

    $this->browserA->post('/players', ['name' => ''], '/players')->assertRedirect('/players');

    $this->browserB->get('/players')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('players/index')->where('errors', []));

    $this->browserA->get('/players')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('errors.name'));
});

test('a session opened with the former passcode is sent to /login once the passcode changes', function () {
    $this->browserA->logIn();
    $this->browserA->get('/')->assertOk();

    // A new APP_PASSCODE after a redeployment.
    config(['app.passcode' => 'nouveau-code-2027']);

    $this->browserA->get('/')->assertRedirect('/login');
});
