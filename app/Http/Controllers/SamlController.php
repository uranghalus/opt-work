<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use LightSaml\Error\LightSamlException;
use SocialiteProviders\Saml2\Provider;
use SocialiteProviders\Saml2\User as Saml2User;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SamlController extends Controller
{
    /**
     * Send the user to the identity provider (SP-initiated SSO).
     */
    public function redirect(): SymfonyRedirect
    {
        return $this->driver()->redirect();
    }

    /**
     * Consume the SAML response and log the user in.
     *
     * Accepts both SP-initiated responses (validated against the relay state
     * stored in the session) and IdP-initiated responses (accepted only when
     * no SP-initiated flow is in progress, and still validated by the
     * signature, issuer and timestamp checks of the SAML provider itself).
     */
    public function acs(): RedirectResponse
    {
        // Decided before resolving the user: the provider pulls the relay
        // state out of the session while validating it, so the information is
        // gone after the first resolution attempt.
        $stateless = ! $this->hasSpInitiatedState();

        try {
            $samlUser = $stateless
                ? $this->statelessSamlUser()
                : $this->samlUser();
        } catch (Throwable $exception) {
            Log::warning('SAML login failed.', [
                'exception' => $exception,
            ]);

            return $this->loginFailure();
        }

        $user = $this->loginUser($samlUser);

        // A stale relay state from an abandoned SP-initiated flow must not
        // make a later IdP-initiated response look state-forged.
        request()->session()->forget('state');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Consume an unsolicited SAML logout request from the identity provider
     * (IdP-initiated single logout) and answer it.
     */
    public function sls(): Response
    {
        $this->logoutLocally();

        try {
            return $this->driver()->logoutResponse();
        } catch (Throwable $exception) {
            Log::warning('SAML logout response failed.', [
                'exception' => $exception,
            ]);

            return redirect()->route('login')->with('status', 'You have been logged out.');
        }
    }

    /**
     * Publish this service provider's SAML metadata for identity provider
     * configuration.
     */
    public function metadata(): Response
    {
        return $this->driver()->getServiceProviderMetadata();
    }

    /**
     * The configured Saml2 Socialite provider.
     */
    protected function driver(): Provider
    {
        /** @var Provider $driver */
        $driver = Socialite::driver('saml2');

        return $driver;
    }

    /**
     * Resolve the SAML user with relay state validation (SP-initiated).
     *
     * @throws Throwable
     */
    protected function samlUser(): Saml2User
    {
        $user = $this->driver()->user();

        assert($user instanceof Saml2User);

        return $user;
    }

    /**
     * Resolve the SAML user without relay state validation (IdP-initiated).
     *
     * @throws Throwable
     */
    protected function statelessSamlUser(): Saml2User
    {
        $user = $this->driver()->stateless()->user();

        assert($user instanceof Saml2User);

        return $user;
    }

    /**
     * Whether this session has an SP-initiated SAML flow in progress.
     */
    protected function hasSpInitiatedState(): bool
    {
        return (bool) request()->session()->get('state');
    }

    /**
     * Find or create the local account for a SAML-authenticated identity and
     * log them in.
     *
     * @throws LightSamlException
     */
    protected function loginUser(Saml2User $samlUser): User
    {
        $email = $samlUser->getEmail();

        if (! $email) {
            throw new LightSamlException('The identity provider did not return an email address.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $samlUser->getName() ?: $email,
                'email' => $email,
                // SAML accounts authenticate through the identity provider, so
                // an unguessable password keeps the column satisfied while
                // making password login impossible.
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::login($user);

        return $user;
    }

    /**
     * Terminate the local session.
     */
    protected function logoutLocally(): void
    {
        Auth::logout();

        request()->session()->invalidate();

        request()->session()->regenerateToken();
    }

    /**
     * Bounce back to the login screen with a generic, user-safe message.
     */
    protected function loginFailure(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['saml' => 'Sign-in with your organization account failed. Please try again.']);
    }
}
