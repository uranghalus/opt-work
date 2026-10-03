<?php

test('guests are redirected to the sso portal', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('saml.redirect'));
});
