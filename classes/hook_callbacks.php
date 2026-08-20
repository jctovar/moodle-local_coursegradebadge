<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge;

use core\hook\output\before_standard_head_html_generation;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

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
