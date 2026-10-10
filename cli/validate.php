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
 * Check wizard definition files before importing them.
 *
 * Usage: php admin/tool/wizards/cli/validate.php <wizard.json | wizards.json | wizards.zip> [...]
 * Exit code 0 when every wizard is valid, 1 otherwise.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Moodle's setup changes the working directory; relative paths start where the command was run.
$startdir = $_SERVER['PWD'] ?? false;

use tool_wizards\local\wizard\validator;

[$options, $paths] = cli_get_params(['help' => false], ['h' => 'help']);
if ($options['help'] || !$paths) {
    cli_writeln("Check wizard definition files.\n\n"
        . "Usage: php admin/tool/wizards/cli/validate.php <wizard.json | wizards.json | wizards.zip> [...]");
    exit($options['help'] ? 0 : 2);
}

$failed = false;
foreach ($paths as $path) {
    if (!str_starts_with($path, '/') && $startdir !== false) {
        $path = $startdir . '/' . $path;
    }
    if (!is_readable($path)) {
        cli_writeln("$path: cannot read");
        $failed = true;
        continue;
    }
    $files = [];
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
        $dir = make_request_directory();
        if ((new zip_packer())->extract_to_pathname($path, $dir) === false) {
            cli_writeln("$path: not a zip file");
            $failed = true;
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (
                strtolower($file->getExtension()) === 'json'
                    && !in_array($file->getFilename(), ['manifest.json', 'wizard.schema.json'], true)
            ) {
                $files[$path . ':' . substr($file->getPathname(), strlen($dir) + 1)] = $file->getPathname();
            }
        }
    } else {
        $files[$path] = $path;
    }
    $documents = [];
    foreach ($files as $label => $file) {
        $documents += \tool_wizards\local\wizard\packager::split((string) file_get_contents($file), $label);
    }
    foreach ($documents as $label => $json) {
        $problems = validator::check_json($json);
        if ($problems) {
            $failed = true;
            cli_writeln("$label: " . count($problems) . " problem(s)");
            foreach ($problems as $problem) {
                cli_writeln("  - $problem");
            }
        } else {
            cli_writeln("$label: valid");
        }
    }
}
exit($failed ? 1 : 0);
