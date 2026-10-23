<?php

use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use Illuminate\Support\Carbon;

/**
 * Business-day deadline math (FR-2.1): urgent 3 / normal 6 hari kerja from
 * the assign date, skipping weekends and the configured holidays. Di-port
 * dari branch feature/extend-deadline (sesi paralel 2026-10-22) saat
 * combine — mengunci addBusinessDays yang dipakai alur extend.
 */
it('gives a normal work order six business days', function () {
    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Normal,
        Carbon::parse('2026-10-19'), // Monday
    );

    expect($deadline->toDateString())->toBe('2026-10-27'); // Tuesday next week
});

it('gives urgent work orders three business days', function () {
    $calculator = new DeadlineCalculator;

    expect($calculator->calculate(WorkOrderCategory::Accident, Carbon::parse('2026-10-19'))->toDateString())
        ->toBe('2026-10-22'); // Thursday
    expect($calculator->calculate(WorkOrderCategory::Owner, Carbon::parse('2026-10-19'))->toDateString())
        ->toBe('2026-10-22'); // Owner is urgent too
});

it('skips weekends', function () {
    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Accident,
        Carbon::parse('2026-10-23'), // Friday
    );

    expect($deadline->toDateString())->toBe('2026-10-28'); // Wed, Sat+Sun skipped
});

it('skips fixed-date holidays from the config', function () {
    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Accident,
        Carbon::parse('2026-10-19'), // Monday
        ['2026-10-21'], // Wednesday is a holiday
    );

    expect($deadline->toDateString())->toBe('2026-10-23'); // Friday
});

it('skips recurring MM-DD holidays from the config', function () {
    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Accident,
        Carbon::parse('2026-12-24'), // Thursday before Christmas
        ['12-25'], // recurring every year
    );

    expect($deadline->toDateString())->toBe('2026-12-30'); // Wed next week
});

it('reads the configured holidays when none are passed', function () {
    config(['holidays' => ['2026-10-21']]);

    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Accident,
        Carbon::parse('2026-10-19'),
    );

    expect($deadline->toDateString())->toBe('2026-10-23');
});

it('counts from the day after the assign date', function () {
    // Assign on a Saturday that is itself a holiday: counting starts at the
    // next business day — Monday is day 1, Wednesday is day 3.
    $deadline = (new DeadlineCalculator)->calculate(
        WorkOrderCategory::Accident,
        Carbon::parse('2026-10-24'), // Saturday
        ['2026-10-24'],
    );

    expect($deadline->toDateString())->toBe('2026-10-28'); // Wednesday
});

it('adds business days to a date for deadline extensions', function () {
    $calculator = new DeadlineCalculator;

    // Friday + 2 business days = Tuesday (weekend skipped).
    expect($calculator->addBusinessDays(Carbon::parse('2026-10-23'), 2)->toDateString())
        ->toBe('2026-10-27');

    // Monday + 3 with Wednesday as a holiday = Friday.
    expect($calculator->addBusinessDays(Carbon::parse('2026-10-19'), 3, ['2026-10-21'])->toDateString())
        ->toBe('2026-10-23');

    // Starting on a weekend: the first business day after it counts as one.
    expect($calculator->addBusinessDays(Carbon::parse('2026-10-24'), 1)->toDateString())
        ->toBe('2026-10-26');

    // Zero days keeps the date as-is.
    expect($calculator->addBusinessDays(Carbon::parse('2026-10-19'), 0)->toDateString())
        ->toBe('2026-10-19');
});
