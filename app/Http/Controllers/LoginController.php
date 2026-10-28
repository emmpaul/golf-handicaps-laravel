<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Support\Passcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the passcode page, or send a browser that already has a session to the home page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (Passcode::isFingerprint($request->session()->get('access.passcode'))) {
            return redirect('/');
        }

        return Inertia::render('login');
    }

    /**
     * Open the session with the shared passcode, under a new session identifier.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // true deletes the former session row: the identifier sent before login is dead.
        $request->session()->regenerate(true);
        $request->session()->put('access.passcode', Passcode::fingerprint());

        return redirect('/');
    }

    /**
     * Close the session of this browser only: the sessions of other devices stay open.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
