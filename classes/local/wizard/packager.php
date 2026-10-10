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

namespace tool_wizards\local\wizard;

/**
 * Export wizards to a zip and import them back.
 *
 * The zip holds, for each wizard, <key>/wizard.json and <key>/pictures/*, plus manifest.json,
 * RUNBOOK.md and wizard.schema.json, so the file is self-contained for anyone (or any AI)
 * improving the wizards before importing them again.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class packager {
    /** @var string The format of a JSON file holding several wizards. */
    const COLLECTION_FORMAT = 'tool_wizards/wizards@1';

    /** @var string Import: replace a wizard with the same key. */
    const REPLACE = 'replace';

    /** @var string Import: import under a new key. */
    const COPY = 'copy';

    /** @var string Import: skip a wizard whose key exists. */
    const SKIP = 'skip';

    /** @var int The largest picture accepted on import, in bytes. */
    const MAX_PICTURE_BYTES = 2097152;

    /** @var string[] Picture extensions accepted on import. */
    const PICTURE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];

    /**
     * Write a zip of wizards.
     *
     * @param int[] $ids the wizards
     * @return string the path of the zip, in a temporary directory
     */
    public static function export(array $ids): string {
        global $CFG;
        $files = [];
        $keys = [];
        $fs = get_file_storage();
        $context = \core\context\system::instance();
        foreach ($ids as $id) {
            $record = repository::get((int) $id);
            if (!$record) {
                continue;
            }
            $keys[] = $record->wizardkey;
            $files[$record->wizardkey . '/wizard.json'] = [repository::encode(repository::definition($record))];
            foreach ($fs->get_area_files($context->id, 'tool_wizards', 'picture', $record->id, 'filename', false) as $file) {
                $files[$record->wizardkey . '/pictures/' . $file->get_filename()] = $file;
            }
        }
        $plugin = \core_plugin_manager::instance()->get_plugin_info('tool_wizards');
        $files['manifest.json'] = [json_encode([
            'format' => validator::FORMAT,
            'plugin' => 'tool_wizards ' . ($plugin->release ?? '') . ' (' . ($plugin->versiondisk ?? '') . ')',
            'site' => $CFG->wwwroot,
            'exported' => date('c'),
            'wizards' => $keys,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];
        $files['RUNBOOK.md'] = $CFG->dirroot . '/admin/tool/wizards/runbook/RUNBOOK.md';
        $files['wizard.schema.json'] = $CFG->dirroot . '/admin/tool/wizards/runbook/wizard.schema.json';

        $path = make_request_directory() . '/wizards.zip';
        (new \zip_packer())->archive_to_pathname($files, $path);
        return $path;
    }

    /**
     * Write one JSON file holding several wizards (a collection), without their uploaded pictures.
     *
     * @param int[] $ids the wizards
     * @return string the JSON
     */
    public static function export_json(array $ids): string {
        $wizards = [];
        foreach ($ids as $id) {
            $record = repository::get((int) $id);
            if ($record) {
                $wizards[] = repository::definition($record);
            }
        }
        return json_encode(
            ['format' => self::COLLECTION_FORMAT, 'wizards' => $wizards],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    /**
     * The wizard definitions in one JSON file: a single wizard, a list of wizards, or a collection
     * ({"format": "tool_wizards/wizards@1", "wizards": [...]}).
     *
     * @param string $json the file's contents
     * @param string $label where it came from, for reports
     * @return array label => one wizard's JSON (a file that is not valid JSON is returned as it is, to be reported)
     */
    public static function split(string $json, string $label): array {
        $data = json_decode($json, true);
        if (is_array($data) && ($data['format'] ?? null) === self::COLLECTION_FORMAT && is_array($data['wizards'] ?? null)) {
            $data = $data['wizards'];
        } else if (!is_array($data) || !array_is_list($data) || !$data) {
            return [$label => $json];
        }
        $out = [];
        foreach (array_values($data) as $i => $doc) {
            $key = is_array($doc) && is_string($doc['key'] ?? null) ? $doc['key'] : '#' . ($i + 1);
            $out[$label . ' › ' . $key] = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $out;
    }

    /**
     * Import wizards from a zip, or from a JSON file holding one wizard or several.
     *
     * Every wizard is validated before anything is written for it; a bad one is reported and skipped.
     *
     * @param string $path the uploaded file
     * @param string $filename its name (for telling a zip from a JSON file)
     * @param string $mode REPLACE, COPY or SKIP, for keys that already exist
     * @return array of ['key' => string, 'result' => created|replaced|copied|skipped|invalid, 'problems' => string[]]
     */
    public static function import(string $path, string $filename, string $mode): array {
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json') {
            $results = [];
            foreach (self::split((string) file_get_contents($path), $filename) as $label => $json) {
                $results[] = self::import_one($json, [], $mode, $label);
            }
            return $results;
        }
        $dir = make_request_directory();
        $extracted = (new \zip_packer())->extract_to_pathname($path, $dir);
        if ($extracted === false) {
            return [['key' => $filename, 'result' => 'invalid', 'problems' => [get_string('import_notzip', 'tool_wizards')]]];
        }
        $results = [];
        $found = false;
        foreach (self::wizard_files($dir) as $jsonpath) {
            $found = true;
            $pictures = [];
            $picdir = dirname($jsonpath) . '/pictures';
            if (is_dir($picdir)) {
                foreach (scandir($picdir) as $name) {
                    if (is_file("$picdir/$name")) {
                        $pictures[$name] = "$picdir/$name";
                    }
                }
            }
            $label = substr($jsonpath, strlen($dir) + 1);
            foreach (self::split((string) file_get_contents($jsonpath), $label) as $onelabel => $json) {
                $results[] = self::import_one($json, $pictures, $mode, $onelabel);
            }
        }
        if (!$found) {
            $problem = get_string('import_nowizards', 'tool_wizards');
            $results[] = ['key' => $filename, 'result' => 'invalid', 'problems' => [$problem]];
        }
        return $results;
    }

    /**
     * The wizard.json files in an extracted zip (also any other *.json except the manifest and schema).
     *
     * @param string $dir the directory
     * @return string[] paths
     */
    protected static function wizard_files(string $dir): array {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $name = $file->getFilename();
            if (
                strtolower($file->getExtension()) === 'json' && !in_array($name, ['manifest.json', 'wizard.schema.json'], true)
                    && !str_starts_with($name, '.') && strpos($file->getPathname(), '__MACOSX') === false
            ) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Import one wizard.
     *
     * @param string $json its definition
     * @param array $pictures picture file name => path
     * @param string $mode REPLACE, COPY or SKIP
     * @param string $label where it came from, for the report
     * @return array result
     */
    protected static function import_one(string $json, array $pictures, string $mode, string $label): array {
        $doc = json_decode($json, true);
        if (!is_array($doc)) {
            return ['key' => $label, 'result' => 'invalid', 'problems' => ['not valid JSON: ' . json_last_error_msg()]];
        }
        $problems = validator::check($doc);
        $problems = array_merge($problems, self::picture_problems($doc, $pictures));
        if ($problems) {
            return ['key' => $doc['key'] ?? $label, 'result' => 'invalid', 'problems' => $problems];
        }
        $existing = repository::get_by_key($doc['key']);
        $result = 'created';
        $id = null;
        $extra = ['status' => repository::STATUS_DRAFT, 'origin' => repository::ORIGIN_IMPORTED];
        if ($existing) {
            if ($mode === self::SKIP) {
                return ['key' => $doc['key'], 'result' => 'skipped', 'problems' => []];
            }
            if ($mode === self::REPLACE) {
                $id = (int) $existing->id;
                // A replaced default stays a default (now edited), so updates still flag it.
                $extra = $existing->origin === repository::ORIGIN_DEFAULT ? [] : ['origin' => repository::ORIGIN_IMPORTED];
                $result = 'replaced';
            } else {
                $doc['key'] = repository::free_key($doc['key']);
                unset($doc['defaultversion']);
                $result = 'copied';
            }
        }
        unset($extra['status']);
        if (!$id) {
            $extra['status'] = repository::STATUS_DRAFT;
        }
        $id = repository::save($doc, $extra, $id);
        self::store_pictures($id, $pictures, $result === 'replaced');
        return ['key' => $doc['key'], 'result' => $result, 'problems' => []];
    }

    /**
     * Problems with a wizard's pictures: every file: picture must be supplied and acceptable.
     *
     * @param array $doc the definition
     * @param array $pictures file name => path
     * @return string[]
     */
    protected static function picture_problems(array $doc, array $pictures): array {
        $problems = [];
        foreach ($pictures as $name => $path) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::PICTURE_EXTENSIONS, true) || clean_param($name, PARAM_FILE) !== $name) {
                $problems[] = "pictures/$name: not an accepted picture (png, jpg, webp, svg, gif)";
            } else if (filesize($path) > self::MAX_PICTURE_BYTES) {
                $problems[] = "pictures/$name: larger than 2 MB";
            }
        }
        array_walk_recursive($doc, function ($value, $key) use (&$problems, $pictures) {
            if (
                $key === 'picture' && is_string($value) && str_starts_with($value, 'file:')
                    && !isset($pictures[substr($value, 5)])
            ) {
                $problems[] = 'picture ' . $value . ': not in the pictures folder';
            }
        });
        return $problems;
    }

    /**
     * Store a wizard's pictures.
     *
     * @param int $id the wizard
     * @param array $pictures file name => path
     * @param bool $replace whether to remove the existing ones first
     */
    public static function store_pictures(int $id, array $pictures, bool $replace): void {
        $fs = get_file_storage();
        $context = \core\context\system::instance();
        if ($replace) {
            $fs->delete_area_files($context->id, 'tool_wizards', 'picture', $id);
        }
        foreach ($pictures as $name => $path) {
            $record = ['contextid' => $context->id, 'component' => 'tool_wizards', 'filearea' => 'picture',
                'itemid' => $id, 'filepath' => '/', 'filename' => $name];
            if ($existing = $fs->get_file($context->id, 'tool_wizards', 'picture', $id, '/', $name)) {
                $existing->delete();
            }
            $fs->create_file_from_pathname($record, $path);
        }
    }
}
