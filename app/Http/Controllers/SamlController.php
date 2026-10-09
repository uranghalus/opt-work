<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Credential\KeyHelper;
use LightSaml\Error\LightSamlException;
use LightSaml\Error\LightSamlSecurityException;
use LightSaml\Error\LightSamlValidationException;
use LightSaml\Model\Metadata\KeyDescriptor;
use LightSaml\Model\Protocol\LogoutResponse;
use SocialiteProviders\Saml2\InvalidSignatureException;
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
            dd($samlUser);
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

        // Redirect based on user role
        if ($user->is_super_admin) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($user->tenant_id) {
            return redirect()->intended(route('dashboard', ['tenant' => $user->tenant_id]));
        }

        // Superadmin without home tenant - go to admin dashboard to pick tenant
        return redirect()->route('admin.dashboard');
    }

    /**
     * Terminate the local session and start a single logout at the identity
     * provider (SP-initiated SLO).
     */
    public function initiateLogout(Request $request): Response
    {
        $nameId = $request->session()->get('saml.name_id');

        $this->logoutLocally();

        if (! $nameId) {
            return redirect('/');
        }

        try {
            return $this->driver()->logoutRequest($nameId);
        } catch (Throwable $exception) {
            Log::warning('SAML single logout request failed.', [
                'exception' => $exception,
            ]);

            return redirect('/');
        }
    }

    /**
     * Single logout service: answers an unsolicited logout request from the
     * identity provider (IdP-initiated SLO), or completes a logout this
     * service provider started (SP-initiated SLO).
     */
    public function sls(): Response
    {
        if (request()->has('SAMLResponse')) {
            return $this->finishInitiatedLogout();
        }

        $this->logoutLocally();

        try {
            return $this->driver()->logoutResponse();
        } catch (Throwable $exception) {
            Log::warning('SAML logout response failed.', [
                'exception' => $exception,
            ]);

            return redirect('/')->with('status', 'You have been logged out.');
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
        // Log raw attributes in debug mode so we can inspect what the IdP sends.
        Log::debug('SAML assertion received.', [
            'id' => $samlUser->getId(),
            'name' => $samlUser->getName(),
            'email' => $samlUser->getEmail(),
            'attributes' => $samlUser->getRaw(),
        ]);

        $email = $this->resolveEmail($samlUser);

        if (! $email) {
            Log::warning('SAML: no email found in assertion.', [
                'id' => $samlUser->getId(),
                'attributes' => $samlUser->getRaw(),
            ]);

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

        // Remember the NameID so SP-initiated single logout can address the
        // identity provider after the local session is gone.
        if ($nameId = $samlUser->getId()) {
            request()->session()->put('saml.name_id', $nameId);
        }

        return $user;
    }

    /**
     * Resolve an email address from the SAML assertion using multiple strategies:
     *
     *  1. Standard attribute map (ClaimTypes, OASIS URIs) via getEmail().
     *  2. The NameID, if it looks like an email address.
     *  3. A first-pass scan of every raw attribute value that looks like an email.
     */
    protected function resolveEmail(Saml2User $samlUser): ?string
    {
        // Strategy 1 – standard mapped attributes.
        if ($email = $samlUser->getEmail()) {
            return $email;
        }

        // Strategy 2 – NameID that happens to be an email address.
        if ($id = $samlUser->getId()) {
            if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
                return $id;
            }
        }

        // Strategy 3 – brute-force scan of every raw attribute value.
        foreach ($samlUser->getRaw() as $attribute) {
            if (! $attribute instanceof Attribute) {
                continue;
            }

            foreach ($attribute->getAllAttributeValues() as $value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        }

        return null;
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
     * Finish a logout this service provider initiated: validate the identity
     * provider's LogoutResponse and land back on the root, where the closed
     * identity provider session yields a fresh login instead of a re-login.
     */
    protected function finishInitiatedLogout(): RedirectResponse
    {
        try {
            $this->validateLogoutResponse($this->receiveLogoutResponse());

            $this->logoutLocally();
        } catch (Throwable $exception) {
            Log::warning('SAML logout response rejected.', [
                'exception' => $exception,
            ]);
        }

        return redirect('/')->with('status', 'You have been logged out.');
    }

    /**
     * Deserialize the LogoutResponse delivered to the single logout service.
     *
     * @throws LightSamlException
     */
    protected function receiveLogoutResponse(): LogoutResponse
    {
        $request = request();

        $bindingFactory = new BindingFactory;
        $bindingType = $bindingFactory->detectBindingType($request);

        if (! $bindingType) {
            throw new LightSamlException('No SAML binding detected on the logout response.');
        }

        $messageContext = new MessageContext;
        $bindingFactory->create($bindingType)->receive($request, $messageContext);

        $message = $messageContext->getMessage();

        if (! $message instanceof LogoutResponse) {
            throw new LightSamlException('The single logout service did not receive a logout response.');
        }

        return $message;
    }

    /**
     * Validate a LogoutResponse against the configured identity provider.
     *
     * @throws Throwable
     */
    protected function validateLogoutResponse(LogoutResponse $logoutResponse): void
    {
        if (! $logoutResponse->getStatus()->isSuccess()) {
            throw new LightSamlValidationException('The identity provider rejected the logout.');
        }

        $issuer = $this->driver()
            ->getIdentityProviderEntityDescriptor()
            ->getEntityID();

        if ($logoutResponse->getIssuer()?->getValue() !== $issuer) {
            throw new LightSamlValidationException('The logout response issuer did not match the identity provider.');
        }

        // The portal registers 'saml/logout' as its SLS while metadata-based
        // setups advertise 'saml/sls'; either is a valid destination here.
        if ($destination = $logoutResponse->getDestination()) {
            $validDestinations = [URL::to('saml/sls'), URL::to('saml/logout')];

            if (! in_array($destination, $validDestinations, true)) {
                throw new LightSamlValidationException('The logout response destination did not match this service provider.');
            }
        }

        $this->validateLogoutResponseSignature($logoutResponse);
    }

    /**
     * Verify the LogoutResponse signature with an identity provider key.
     *
     * @throws InvalidSignatureException
     */
    protected function validateLogoutResponseSignature(LogoutResponse $logoutResponse): void
    {
        $signatureReader = $logoutResponse->getSignature();

        if (! $signatureReader) {
            throw new InvalidSignatureException('The logout response had no available signature');
        }

        $idpSsoDescriptor = $this->driver()
            ->getIdentityProviderEntityDescriptor()
            ->getFirstIdpSsoDescriptor();

        $keyDescriptors = array_merge(
            $idpSsoDescriptor->getAllKeyDescriptorsByUse(KeyDescriptor::USE_SIGNING),
            $idpSsoDescriptor->getAllKeyDescriptorsByUse(null),
        );

        foreach ($keyDescriptors as $keyDescriptor) {
            $key = KeyHelper::createPublicKey($keyDescriptor->getCertificate());

            try {
                if ($signatureReader->validate($key)) {
                    return;
                }
            } catch (LightSamlSecurityException) {
                continue;
            }
        }

        throw new InvalidSignatureException('The signature of the logout response could not be verified');
    }

    /**
     * Bounce back to the root (which re-initiates SSO) with a generic,
     * user-safe message.
     */
    protected function loginFailure(): RedirectResponse
    {
        return redirect('/')
            ->withErrors(['saml' => 'Sign-in with your organization account failed. Please try again.']);
    }
}
