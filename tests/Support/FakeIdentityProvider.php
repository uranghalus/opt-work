<?php

namespace Tests\Support;

use DateTime;
use LightSaml\Binding\BindingFactory;
use LightSaml\ClaimTypes;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Credential\KeyHelper;
use LightSaml\Credential\X509Certificate;
use LightSaml\Credential\X509Credential;
use LightSaml\Helper;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\AudienceRestriction;
use LightSaml\Model\Assertion\AuthnContext;
use LightSaml\Model\Assertion\AuthnStatement;
use LightSaml\Model\Assertion\Conditions;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Assertion\SubjectConfirmation;
use LightSaml\Model\Assertion\SubjectConfirmationData;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\Response;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\XmlDSig\SignatureWriter;
use LightSaml\SamlConstants;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Builds signed SAML messages for a fake identity provider so the full
 * Socialite Saml2 flow can be exercised in feature tests without a real
 * identity provider.
 */
class FakeIdentityProvider
{
    public const ENTITY_ID = 'https://idp.test/metadata';

    public const SSO_URL = 'https://idp.test/sso';

    public const SLO_URL = 'https://idp.test/slo';

    public const STATE = 'fake-relay-state';

    public const EMAIL = 'janet.garcia@optigate.test';

    public const NAME = 'Janet Garcia';

    /**
     * Identity provider metadata equivalent to the metadata the real
     * OptiGate identity provider publishes.
     */
    public static function metadataXml(): string
    {
        $entityId = self::ENTITY_ID;
        $ssoUrl = self::SSO_URL;
        $sloUrl = self::SLO_URL;
        $certificate = self::certificateBody('idp_saml.crt');

        return <<<XML
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="{$entityId}">
    <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>{$certificate}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="{$sloUrl}"/>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</md:NameIDFormat>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="{$ssoUrl}"/>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="{$ssoUrl}"/>
    </md:IDPSSODescriptor>
</md:EntityDescriptor>
XML;
    }

    /**
     * The assertion consumer service URL exactly as the service provider
     * advertises it (derived from the application URL).
     */
    public static function acsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/saml/acs';
    }

    /**
     * The service provider entity ID exactly as the service provider
     * advertises it (derived from the application URL).
     */
    public static function spEntityId(): string
    {
        return rtrim((string) config('app.url'), '/').'/auth/saml2';
    }

    /**
     * The single logout service URL exactly as the service provider
     * advertises it (derived from the application URL).
     */
    public static function slsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/saml/sls';
    }

    /**
     * A URL for the service provider's assertion consumer service carrying a
     * signed SAML response (HTTP-Redirect binding style).
     *
     * @param  array{success?: bool, signed_by?: 'idp'|'sp', issuer?: string, state?: string, name_id?: string, in_response_to?: string|null, email?: string|null, name?: string|null, first_name?: string|null, last_name?: string|null}  $options
     */
    public static function assertionResponseUrl(array $options = []): string
    {
        $xml = self::serialize(self::buildResponse($options));

        return self::acsUrl().'?'.http_build_query([
            'SAMLResponse' => base64_encode(gzdeflate($xml)),
            'RelayState' => $options['state'] ?? self::STATE,
        ]);
    }

    /**
     * A URL for the service provider's single logout service carrying a
     * signed SAML logout request (HTTP-Redirect binding), as an identity
     * provider would deliver it to start a single logout.
     *
     * @param  array{signed_by?: 'idp'|'sp', name_id?: string}  $options
     */
    public static function logoutRequestUrl(array $options = []): string
    {
        $logoutRequest = new LogoutRequest;
        $logoutRequest
            ->setID(Helper::generateID())
            ->setIssueInstant(new DateTime)
            ->setDestination(self::slsUrl())
            ->setIssuer(new Issuer(self::ENTITY_ID))
            ->setNameID(new NameID($options['name_id'] ?? self::EMAIL, SamlConstants::NAME_ID_FORMAT_EMAIL))
            ->setRelayState(self::STATE);

        self::sign($logoutRequest, $options);

        $messageContext = new MessageContext;
        $messageContext->setMessage($logoutRequest);

        $binding = (new BindingFactory)->create(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);

        /** @var RedirectResponse $redirect */
        $redirect = $binding->send($messageContext);

        return $redirect->getTargetUrl();
    }

    /**
     * Decode the AuthnRequest carried by a redirect to the identity provider.
     */
    public static function decodeAuthnRequest(string $url): AuthnRequest
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $xml = (string) gzinflate((string) base64_decode((string) ($query['SAMLRequest'] ?? ''), true));

        /** @var AuthnRequest $authnRequest */
        $authnRequest = SamlMessage::fromXML($xml, new DeserializationContext);

        return $authnRequest;
    }

    /**
     * Raw contents of a test fixture file (certificate or private key).
     */
    public static function fixture(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/SAML/'.$file);
    }

    /**
     * @param  array{success?: bool, signed_by?: 'idp'|'sp', issuer?: string, name_id?: string, in_response_to?: string|null, email?: string|null, name?: string|null, first_name?: string|null, last_name?: string|null}  $options
     */
    private static function buildResponse(array $options): Response
    {
        $response = new Response;
        $response
            ->setID(Helper::generateID())
            ->setIssueInstant(new DateTime)
            ->setDestination(self::acsUrl())
            ->setIssuer(new Issuer($options['issuer'] ?? self::ENTITY_ID))
            ->setStatus((new Status)->setSuccess());

        if (! ($options['success'] ?? true)) {
            $response->setStatus(new Status(
                new StatusCode(SamlConstants::STATUS_RESPONDER),
                'The identity provider rejected the request.',
            ));

            return $response;
        }

        $response->addAssertion(self::buildAssertion($options));
        self::sign($response, $options);

        return $response;
    }

    /**
     * @param  array{signed_by?: 'idp'|'sp', issuer?: string, name_id?: string, in_response_to?: string|null, email?: string|null, name?: string|null, first_name?: string|null, last_name?: string|null}  $options
     */
    private static function buildAssertion(array $options): Assertion
    {
        $assertion = new Assertion;
        $assertion
            ->setId(Helper::generateID())
            ->setVersion(SamlConstants::VERSION_20)
            ->setIssueInstant(new DateTime)
            ->setIssuer(new Issuer($options['issuer'] ?? self::ENTITY_ID))
            ->setConditions(
                (new Conditions)
                    ->setNotBefore(new DateTime('-1 minute'))
                    ->setNotOnOrAfter(new DateTime('+5 minutes'))
                    ->addItem(new AudienceRestriction(self::spEntityId()))
            );

        $subjectConfirmation = new SubjectConfirmation;
        $subjectConfirmation->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER);

        $subjectConfirmationData = new SubjectConfirmationData;
        $subjectConfirmationData
            ->setNotOnOrAfter(new DateTime('+5 minutes'))
            ->setRecipient(self::acsUrl());

        if ($options['in_response_to'] ?? null) {
            $subjectConfirmationData->setInResponseTo($options['in_response_to']);
        }

        $subjectConfirmation->setSubjectConfirmationData($subjectConfirmationData);

        $subject = new Subject;
        $subject->setNameID(new NameID($options['name_id'] ?? self::EMAIL, SamlConstants::NAME_ID_FORMAT_EMAIL));
        $subject->addSubjectConfirmation($subjectConfirmation);

        $assertion->setSubject($subject);
        $assertion->addItem(self::buildAuthnStatement());
        $assertion->addItem(self::buildAttributeStatement($options));
        self::sign($assertion, $options);

        return $assertion;
    }

    private static function buildAuthnStatement(): AuthnStatement
    {
        $authnStatement = new AuthnStatement;
        $authnStatement
            ->setAuthnInstant(new DateTime('-5 minutes'))
            ->setSessionIndex(Helper::generateID())
            ->setAuthnContext(
                (new AuthnContext)->setAuthnContextClassRef(SamlConstants::AUTHN_CONTEXT_PASSWORD)
            );

        return $authnStatement;
    }

    /**
     * @param  array{email?: string|null, name?: string|null, first_name?: string|null, last_name?: string|null}  $options
     */
    private static function buildAttributeStatement(array $options): AttributeStatement
    {
        $attributes = [
            ClaimTypes::EMAIL_ADDRESS => $options['email'] ?? self::EMAIL,
            ClaimTypes::NAME => $options['name'] ?? self::NAME,
            ClaimTypes::GIVEN_NAME => $options['first_name'] ?? 'Janet',
            ClaimTypes::SURNAME => $options['last_name'] ?? 'Garcia',
        ];

        $attributeStatement = new AttributeStatement;

        foreach ($attributes as $name => $value) {
            if ($value !== null) {
                $attributeStatement->addAttribute(new Attribute($name, $value));
            }
        }

        return $attributeStatement;
    }

    /**
     * @param  array{signed_by?: 'idp'|'sp'}  $options
     */
    private static function sign(mixed $message, array $options): void
    {
        $credential = ($options['signed_by'] ?? 'idp') === 'sp'
            ? self::spCredential()
            : self::idpCredential();

        $message->setSignature(new SignatureWriter(
            $credential->getCertificate(),
            $credential->getPrivateKey(),
            XMLSecurityDSig::SHA256,
        ));
    }

    private static function idpCredential(): X509Credential
    {
        return new X509Credential(
            self::certificate('idp_saml.crt'),
            KeyHelper::createPrivateKey(self::fixture('idp_saml.pem'), null, false, XMLSecurityKey::RSA_SHA256),
        );
    }

    private static function spCredential(): X509Credential
    {
        return new X509Credential(
            self::certificate('sp_saml.crt'),
            KeyHelper::createPrivateKey(self::fixture('sp_saml.pem'), null, false, XMLSecurityKey::RSA_SHA256),
        );
    }

    private static function certificate(string $file): X509Certificate
    {
        return (new X509Certificate)->loadPem(self::fixture($file));
    }

    private static function certificateBody(string $file): string
    {
        return str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"],
            '',
            self::fixture($file),
        );
    }

    private static function serialize(mixed $model): string
    {
        $context = new SerializationContext;
        $model->serialize($context->getDocument(), $context);

        return (string) $context->getDocument()->saveXML();
    }
}
