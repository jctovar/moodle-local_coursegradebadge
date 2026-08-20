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
     * The callback runs without fatalling, on the dashboard and outside it.
     *
     * Regression test: the guard called duringinitialinstall(), which does not
     * exist (the core function is during_initial_install()). Because the callback
     * had never been registered, the undefined function only surfaced once the
     * registration was fixed, and then it fatalled every page including the
     * upgrade screen. This exercises the callback for real instead of only
     * testing its helpers.
     *
     * @return void
     */
    public function test_callback_runs_without_error(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));

        // Outside the dashboard the callback must bail out quietly.
        $PAGE->set_url('/admin/index.php');
        \local_coursegradebadge\hook_callbacks::before_standard_head_html_generation($hook);

        // On the dashboard it must reach js_call_amd() without raising anything.
        $page = new \moodle_page();
        $page->set_url('/my/index.php');
        $page->set_context(\context_system::instance());
        $PAGE = $page;
        \local_coursegradebadge\hook_callbacks::before_standard_head_html_generation($hook);

        // Reaching this point without an Error or exception is the assertion.
        $this->assertTrue(true);
    }

    /**
     * The callback is actually registered with the hook manager.
     *
     * Regression test: db/hooks.php must define $callbacks. Moodle's hook manager
     * includes the file and reads that exact variable, so naming it $hooks left the
     * listener silently unregistered and the AMD module never loaded.
     *
     * @return void
     */
    public function test_callback_is_registered(): void {
        $this->resetAfterTest();

        $callbacks = \core\hook\manager::get_instance()->get_callbacks_for_hook(
            \core\hook\output\before_standard_head_html_generation::class
        );

        $callables = array_column($callbacks, 'callback');
        $this->assertContains(
            hook_callbacks::class . '::before_standard_head_html_generation',
            $callables,
            'The plugin hook callback is not registered; check that db/hooks.php defines $callbacks.'
        );
    }

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
