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
 * Resolves the total course grade for a user, using aggregated queries.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_coursegradebadge;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

class grade_resolver {

    public const MAX_COURSES = 50;

    public static function get_course_grades(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (count($courseids) > self::MAX_COURSES) {
            $courseids = array_slice($courseids, 0, self::MAX_COURSES);
        }
        if (empty($courseids)) {
            return [];
        }

        $result = [];
        foreach ($courseids as $courseid) {
            $result[$courseid] = (object)[
                'courseid' => $courseid,
                'formatted' => null,
                'percentage' => null,
                'reason' => 'nograde',
            ];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $courses = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, showgrades');
        foreach ($result as $courseid => $entry) {
            if (!isset($courses[$courseid]) || !$courses[$courseid]->showgrades) {
                $entry->reason = 'hidden';
            }
        }

        $itemrecords = $DB->get_records_select('grade_items', "itemtype = 'course' AND courseid $insql", $inparams);
        if (empty($itemrecords)) {
            return $result;
        }

        $gradeitembycourse = [];
        foreach ($itemrecords as $itemrecord) {
            $gradeitembycourse[$itemrecord->courseid] = new \grade_item($itemrecord, false);
        }

        $sql = "SELECT gi.courseid, gg.finalgrade, gg.hidden AS gradehidden, gg.excluded
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid AND gi.itemtype = 'course' AND gi.courseid $insql";
        $params = array_merge($inparams, ['userid' => $userid]);
        $graderecords = $DB->get_records_sql($sql, $params);

        foreach ($graderecords as $record) {
            $courseid = (int)$record->courseid;
            if (!isset($result[$courseid]) || $result[$courseid]->reason === 'hidden') {
                continue;
            }
            $gradeitem = $gradeitembycourse[$courseid];
            if ($record->gradehidden || $gradeitem->is_hidden()) {
                $result[$courseid]->reason = 'hidden';
                continue;
            }
            if ($record->excluded || $record->finalgrade === null) {
                continue;
            }
            $result[$courseid]->formatted = grade_format_gradevalue($record->finalgrade, $gradeitem);
            if ($gradeitem->grademax > $gradeitem->grademin) {
                $result[$courseid]->percentage =
                    round((($record->finalgrade - $gradeitem->grademin) /
                    ($gradeitem->grademax - $gradeitem->grademin)) * 100, 2);
            }
            $result[$courseid]->reason = 'ok';
        }

        return $result;
    }
}
