<?php

use App\Services\WahaClient;

test('normalizes local numbers to the WhatsApp country format', function () {
    expect(WahaClient::normalizeChatId('0812 3456 7890'))
        ->toBe('6281234567890@c.us');
});

test('passes a raw WhatsApp chat id through unchanged', function () {
    expect(WahaClient::normalizeChatId('6281234567890@c.us'))
        ->toBe('6281234567890@c.us');
});

test('keeps the digits when the number already carries the country code', function () {
    expect(WahaClient::normalizeChatId('+62 812-3456-7890'))
        ->toBe('6281234567890@c.us');
});

test('returns null when the phone number has no usable digits', function () {
    expect(WahaClient::normalizeChatId('n/a'))->toBeNull()
        ->and(WahaClient::normalizeChatId('   '))->toBeNull();
});
