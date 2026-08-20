<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursegradebadge_get_grades' => [
        'classname' => \local_coursegradebadge\external\get_grades::class,
        'methodname' => 'execute',
        'description' => 'Returns the total course grade for the current user in the given courses.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
