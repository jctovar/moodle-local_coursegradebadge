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
 * Behat step definitions for the course grade badge plugin.
 *
 * @package    local_coursegradebadge
 * @category   test
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for the course grade badge plugin.
 *
 * @package    local_coursegradebadge
 * @category   test
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_coursegradebadge extends behat_base {
    /**
     * Sets the total course grade of a user, overriding the course grade item.
     *
     * The course grade item has no itemname, so the core "grade grades" generator
     * cannot target it. Overriding it keeps the scenario deterministic: no lazy
     * regrade has to happen for the total to be readable.
     *
     * @Given /^the course total grade for "(?P<username>[^"]*)" in course "(?P<shortname>[^"]*)" is "(?P<grade>[^"]*)"$/
     * @param string $username Username of the graded user.
     * @param string $shortname Course short name.
     * @param string $grade The total grade to set.
     * @return void
     */
    public function the_course_total_grade_is(string $username, string $shortname, string $grade): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $courseitem = grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($user->id, (float) $grade, 'behat');
    }
}
