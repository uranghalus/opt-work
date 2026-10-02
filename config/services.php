<?php

use LightSaml\SamlConstants;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'saml2' => [
        // Static IdP configuration — no live HTTP fetch needed at runtime.
        // Using env values avoids the cURL SSL error that occurred when
        // fetching the metadata URL from gate.appdutamall.com.
        'metadata'    => null,
        'entityid'    => env('SAML_IDP_ENTITYID'),
        'certificate' => env('SAML_X509_CERT'),
        'acs'         => env('SAML_SSO_URL'),
        'slo'         => env('SAML_SLO_URL'),

        /*
        | Service provider (this application) endpoints.
        |
        | The assertion consumer service serves both HTTP-POST assertions and
        | HTTP-Redirect assertions, so SAML messages are accepted regardless
        | of which binding the identity provider chooses.
        |
        | sp_entityid must match exactly what is registered in the Identity Provider portal.
        */
        'sp_entityid' => env('SAML_SP_ENTITYID'),
        'sp_acs'      => 'saml/acs',
        'sp_sls'      => 'saml/logout',

        // Both ACS bindings are advertised; initiate with HTTP-Redirect.
        'sp_default_binding_method' => SamlConstants::BINDING_SAML2_HTTP_REDIRECT,
    ],

];
