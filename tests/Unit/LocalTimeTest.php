<?php

use App\Domain\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Tests\TestCase;

// LocalTime lit config('app.display_timezone') : l'application doit être démarrée.
pest()->extend(TestCase::class);

test('storage stays in UTC and display defaults to America/Montreal', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('America/Montreal');
});

test('a winter evening instant is formatted in local standard time', function () {
    expect(LocalTime::format(CarbonImmutable::parse('2026-11-02T04:30:00Z')))->toBe('2026-11-01 23:30');
});

test('a summer evening instant is formatted in local daylight time', function () {
    expect(LocalTime::format(CarbonImmutable::parse('2026-07-15T03:30:00Z')))->toBe('2026-07-14 23:30');
});

test('a raw column string without zone is read as UTC before conversion', function () {
    expect(LocalTime::date('2026-11-02 04:30:00'))->toBe('2026-11-01');
});

test('an instant in another zone is converted to the display zone', function () {
    expect(LocalTime::format(CarbonImmutable::parse('2026-11-02T00:30:00', 'Europe/Paris')))->toBe('2026-11-01 18:30');
});

test('changing the display timezone setting changes the formatted output', function () {
    config(['app.display_timezone' => 'Europe/Paris']);

    expect(LocalTime::format(CarbonImmutable::parse('2026-11-02T04:30:00Z')))->toBe('2026-11-02 05:30');
});

test('the start of a summer day is local midnight expressed in UTC', function () {
    $start = LocalTime::startOfDay('2026-07-15');

    expect($start->getTimezone()->getName())->toBe('UTC')
        ->and($start->toIso8601ZuluString())->toBe('2026-07-15T04:00:00Z');
});

test('the day back to standard time spans 25 hours', function () {
    $start = LocalTime::startOfDay('2026-11-01');
    $end = LocalTime::startOfDay('2026-11-02');

    expect($start->toIso8601ZuluString())->toBe('2026-11-01T04:00:00Z')
        ->and($end->toIso8601ZuluString())->toBe('2026-11-02T05:00:00Z')
        ->and((int) $start->diffInHours($end))->toBe(25);
});

test('the day forward to daylight time spans 23 hours', function () {
    $start = LocalTime::startOfDay('2026-03-08');
    $end = LocalTime::startOfDay('2026-03-09');

    expect($start->toIso8601ZuluString())->toBe('2026-03-08T05:00:00Z')
        ->and($end->toIso8601ZuluString())->toBe('2026-03-09T04:00:00Z')
        ->and((int) $start->diffInHours($end))->toBe(23);
});

test('a day that does not exist is rejected instead of rolling over', function () {
    LocalTime::startOfDay('2026-13-45');
})->throws(InvalidArgumentException::class, 'Invalid date "2026-13-45": expected an existing Y-m-d day.');

test('a string that is not a date is rejected by Carbon strict parsing', function () {
    LocalTime::startOfDay('not-a-date');
})->throws(InvalidFormatException::class, 'A four digit year could not be found');
