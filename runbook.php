<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Download the runbook for improving wizards with AI, with the definition schema.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_wizards_manage');
$path = make_request_directory() . '/runbook.zip';
$dir = $CFG->dirroot . '/admin/tool/wizards/runbook';
(new zip_packer())->archive_to_pathname([
    'RUNBOOK.md' => $dir . '/RUNBOOK.md',
    'AI-PROMPT.md' => $dir . '/AI-PROMPT.md',
    'wizard.schema.json' => $dir . '/wizard.schema.json',
    'example-quiz.json' => $CFG->dirroot . '/admin/tool/wizards/defaults/quiz.json',
], $path);
send_file($path, 'wizards-runbook.zip', 0, 0, false, true, 'application/zip');
