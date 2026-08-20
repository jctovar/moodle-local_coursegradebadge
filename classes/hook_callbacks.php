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

        if (duringinitialinstall() || !isloggedin() || isguestuser()) {
            return;
        }

        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        if (!in_array($path, ['/my/index.php', '/my/courses.php'], true)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_coursegradebadge/injector', 'init');
    }
}
