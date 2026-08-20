<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$hooks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_coursegradebadge\hook_callbacks::class, 'before_standard_head_html_generation'],
    ],
];
