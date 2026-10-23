<?php

use Illuminate\Support\Carbon;

/**
 * D2 — domain jam operasional (design .scratch/wo-review-decisions/design.md):
 * "tanggal assign", "hari ini", label deadline, dan command eskalasi harus
 * terukur di jam cabang operasional (WITA, Asia/Makassar) — bukan UTC.
 */
it('runs on the WITA operational clock', function () {
    expect(config('app.timezone'))->toBe('Asia/Makassar');
    expect(now()->format('P'))->toBe('+08:00');
    expect(Carbon::today()->format('P'))->toBe('+08:00');
});
