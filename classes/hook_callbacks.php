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
 * Hook callbacks for the plugin.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursegradebadge;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks used to load the badge injector on the dashboard.
 *
 * @package    local_coursegradebadge
 * @copyright  2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Loads the badge injector AMD module on the dashboard pages only.
     *
     * @param before_standard_head_html_generation $hook The hook instance.
     * @return void
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }

        if (!self::is_dashboard_url($PAGE->url)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_coursegradebadge/injector', 'init');
    }

    /**
     * Whether the given URL is one of the dashboard pages the badge belongs on.
     *
     * The comparison is built from moodle_url rather than from literal paths, so
     * it keeps working when Moodle is served from a subdirectory and get_path()
     * returns something like '/2026-2/my/index.php'.
     *
     * @param \moodle_url|null $url The page URL, or null when the page has none.
     * @return bool True when the URL is the dashboard or the my courses page.
     */
    public static function is_dashboard_url(?\moodle_url $url): bool {
        if ($url === null) {
            return false;
        }

        $dashboards = [
            (new \moodle_url('/my/index.php'))->get_path(),
            (new \moodle_url('/my/courses.php'))->get_path(),
        ];

        return in_array($url->get_path(), $dashboards, true);
    }
}
