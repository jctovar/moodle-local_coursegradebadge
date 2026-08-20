<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External function returning course total grades for the current user.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_coursegradebadge\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_coursegradebadge\grade_resolver;

defined('MOODLE_INTERNAL') || die();

class get_grades extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Course ids to fetch the total grade for (max 50)', VALUE_DEFAULT, []
            ),
        ]);
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'grades' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'formatted' => new external_value(PARAM_TEXT, 'Formatted grade or null', VALUE_OPTIONAL, null),
                    'percentage' => new external_value(PARAM_FLOAT, 'Percentage 0-100 or null', VALUE_OPTIONAL, null),
                    'gradeurl' => new external_value(PARAM_URL, 'URL to the user grade report or null', VALUE_OPTIONAL, null),
                    'reason' => new external_value(PARAM_ALPHA, 'ok|nograde|hidden|nopermission|error'),
                ])
            ),
            'warnings' => new external_warnings(),
        ]);
    }

    public static function execute(array $courseids): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        $courseids = array_slice(array_values(array_unique($params['courseids'])), 0, grade_resolver::MAX_COURSES);

        $grades = [];
        $warnings = [];
        $permitted = [];

        foreach ($courseids as $courseid) {
            try {
                self::validate_context(context_course::instance($courseid));
            } catch (\moodle_exception $e) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'error',
                ];
                $warnings[] = [
                    'item' => 'course',
                    'itemid' => $courseid,
                    'warningcode' => 'contexterror',
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            if (has_capability('moodle/grade:viewall', context_course::instance($courseid))) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'nopermission',
                ];
                continue;
            }

            if (!has_capability('local/coursegradebadge:view', context_course::instance($courseid))) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'nopermission',
                ];
                continue;
            }

            $permitted[] = $courseid;
        }

        $resolved = empty($permitted) ? [] : grade_resolver::get_course_grades((int)$USER->id, $permitted);

        foreach ($resolved as $entry) {
            $item = [
                'courseid' => $entry->courseid,
                'reason' => $entry->reason,
            ];
            if ($entry->reason === 'ok') {
                $item['formatted'] = $entry->formatted;
                $item['percentage'] = $entry->percentage;
                $item['gradeurl'] = (new \moodle_url('/grade/report/user/index.php',
                    ['id' => $entry->courseid]))->out(false);
            }
            $grades[] = $item;
        }

        return ['grades' => $grades, 'warnings' => $warnings];
    }
}
