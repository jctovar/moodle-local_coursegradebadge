<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
