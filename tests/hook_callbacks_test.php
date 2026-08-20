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
 * Tests for the plugin hook callbacks.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursegradebadge;

/**
 * Tests for the plugin hook callbacks.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursegradebadge\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * The dashboard is recognised when Moodle is served from the web root.
     *
     * @return void
     */
    public function test_dashboard_recognised_at_web_root(): void {
        $this->resetAfterTest();
        global $CFG;
        $CFG->wwwroot = 'https://example.com';

        $this->assertTrue(hook_callbacks::is_dashboard_url(new \moodle_url('/my/index.php')));
        $this->assertTrue(hook_callbacks::is_dashboard_url(new \moodle_url('/my/courses.php')));
    }

    /**
     * The dashboard is recognised when Moodle lives in a subdirectory.
     *
     * Regression test: comparing get_path() against a hardcoded '/my/index.php'
     * fails on these installs, because moodle_url prefixes $CFG->wwwroot and the
     * path becomes '/2026-2/my/index.php'.
     *
     * @return void
     */
    public function test_dashboard_recognised_in_subdirectory(): void {
        $this->resetAfterTest();
        global $CFG;
        $CFG->wwwroot = 'https://example.com/2026-2';

        $url = new \moodle_url('/my/index.php');
        $this->assertSame('/2026-2/my/index.php', $url->get_path());
        $this->assertTrue(hook_callbacks::is_dashboard_url($url));
        $this->assertTrue(hook_callbacks::is_dashboard_url(new \moodle_url('/my/courses.php')));
    }

    /**
     * Pages other than the dashboard are rejected, in both layouts.
     *
     * @return void
     */
    public function test_other_pages_rejected(): void {
        $this->resetAfterTest();
        global $CFG;

        foreach (['https://example.com', 'https://example.com/2026-2'] as $wwwroot) {
            $CFG->wwwroot = $wwwroot;
            $this->assertFalse(hook_callbacks::is_dashboard_url(new \moodle_url('/course/view.php', ['id' => 1])));
            $this->assertFalse(hook_callbacks::is_dashboard_url(new \moodle_url('/grade/report/user/index.php')));
            $this->assertFalse(hook_callbacks::is_dashboard_url(null));
        }
    }
}
