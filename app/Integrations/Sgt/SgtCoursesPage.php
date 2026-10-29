<?php

namespace App\Integrations\Sgt;

use App\Domain\Courses\Sgt\SgtCourse;
use JsonException;

/**
 * The course list of the /courses page of Simulator Golf Tour (design D3): the JSON block
 * `<script id='courses-data'>`, whose rows are `[id, name, location, par, difficulty, …]`.
 */
final class SgtCoursesPage
{
    private const string COURSES_DATA = '/<script id=[\'"]courses-data[\'"][^>]*>(.*?)<\/script>/s';

    /**
     * @return list<SgtCourse>
     *
     * @throws SgtUnavailable when the block is missing or a single row is out of shape
     */
    public static function parse(string $html): array
    {
        if (preg_match(self::COURSES_DATA, $html, $block) !== 1) {
            throw new SgtUnavailable('The SGT page has no course list');
        }

        try {
            $data = json_decode($block[1], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SgtUnavailable('The SGT course list is unreadable', previous: $exception);
        }

        if (! is_array($data)
            || ! is_string($data['cardUrl'] ?? null) || ! str_contains($data['cardUrl'], '{COURSEID}')
            || ! is_array($data['courses'] ?? null) || ! array_is_list($data['courses'])) {
            throw new SgtUnavailable('The SGT course list is unreadable');
        }

        return array_map(self::course(...), $data['courses']);
    }

    /**
     * @throws SgtUnavailable
     */
    private static function course(mixed $row): SgtCourse
    {
        if (! is_array($row) || ! array_is_list($row) || count($row) < 4
            || ! is_int($row[0]) || ! is_string($row[1]) || ! is_string($row[2]) || ! is_int($row[3])) {
            throw new SgtUnavailable('The SGT course list is unreadable');
        }

        return new SgtCourse($row[0], $row[1], $row[2], $row[3]);
    }
}
