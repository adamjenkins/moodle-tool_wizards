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
 * The default wizards shipped in defaults/*.json, and keeping a site's copies up to date.
 *
 * - A default not yet on the site is added, enabled, at the end of the order.
 * - An unedited default is replaced when a newer version ships.
 * - An edited default is left alone and flagged: the admin can compare, reset or keep theirs.
 * - A default no longer shipped is kept and marked retired, so the admin may delete it.
 *
 * Status and order live on the row, not in the definition, so switching a default off is not an edit.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defaults {
    /**
     * The shipped definitions.
     *
     * @return array key => definition
     */
    public static function shipped(): array {
        global $CFG;
        $out = [];
        foreach (glob($CFG->dirroot . '/admin/tool/wizards/defaults/*.json') ?: [] as $file) {
            $doc = json_decode(file_get_contents($file), true);
            if (is_array($doc) && isset($doc['key'])) {
                $out[$doc['key']] = $doc;
            }
        }
        ksort($out);
        // The order teachers see them in, as before definitions existed.
        $order = ['course', 'file', 'slides', 'picture', 'page', 'forum', 'glossary', 'quiz', 'assignment', 'link',
            'folder', 'book', 'choice', 'feedback', 'database', 'wiki', 'lesson', 'workshop', 'h5p', 'scorm',
            'contentpackage', 'externaltool', 'bigbluebutton', 'subsection', 'questionbank'];
        $rank = fn($key) => ($pos = array_search($key, $order, true)) === false ? 99 : $pos;
        uksort($out, fn($a, $b) => $rank($a) <=> $rank($b));
        return $out;
    }

    /**
     * Bring the site's defaults up to date with the shipped ones.
     */
    public static function sync(): void {
        global $DB;
        $shipped = self::shipped();
        foreach ($shipped as $key => $doc) {
            $version = (int) ($doc['defaultversion'] ?? 1);
            $record = $DB->get_record(repository::TABLE, ['wizardkey' => $key]);
            if (!$record) {
                try {
                    repository::save($doc, ['status' => repository::STATUS_ENABLED, 'origin' => repository::ORIGIN_DEFAULT,
                        'shippedversion' => $version, 'shippedhash' => repository::hash($doc)]);
                } catch (invalid_definition $e) {
                    debugging('tool_wizards: shipped default ' . $key . ' is not valid: ' . implode('; ', $e->problems));
                }
                continue;
            }
            if ($record->origin !== repository::ORIGIN_DEFAULT || $version <= (int) $record->shippedversion) {
                continue;
            }
            if (!repository::is_edited($record)) {
                repository::save($doc, ['shippedversion' => $version, 'shippedhash' => repository::hash($doc),
                    'newerversion' => 0, 'retired' => 0], (int) $record->id);
            } else if ((int) $record->newerversion < $version) {
                $DB->set_field(repository::TABLE, 'newerversion', $version, ['id' => $record->id]);
            }
        }
        foreach ($DB->get_records(repository::TABLE, ['origin' => repository::ORIGIN_DEFAULT]) as $record) {
            $retired = isset($shipped[$record->wizardkey]) ? 0 : 1;
            if ((int) $record->retired !== $retired) {
                $DB->set_field(repository::TABLE, 'retired', $retired, ['id' => $record->id]);
            }
        }
        repository::changed();
    }

    /**
     * Replace a default with the shipped version, losing local edits.
     *
     * @param int $id the wizard
     */
    public static function reset(int $id): void {
        $record = repository::get($id);
        $doc = self::shipped()[$record->wizardkey] ?? null;
        if (!$record || $record->origin !== repository::ORIGIN_DEFAULT || !$doc) {
            return;
        }
        repository::save($doc, ['shippedversion' => (int) ($doc['defaultversion'] ?? 1),
            'shippedhash' => repository::hash($doc), 'newerversion' => 0], $id);
    }

    /**
     * Keep the local edits and stop flagging the newer default (until the next one).
     *
     * @param int $id the wizard
     */
    public static function keep_mine(int $id): void {
        global $DB;
        $record = repository::get($id);
        if ($record && (int) $record->newerversion > 0) {
            $DB->set_field(repository::TABLE, 'shippedversion', (int) $record->newerversion, ['id' => $id]);
            $DB->set_field(repository::TABLE, 'newerversion', 0, ['id' => $id]);
            repository::changed();
        }
    }

    /**
     * The differences between two definitions, in words.
     *
     * @param array $mine the site's definition
     * @param array $theirs the shipped one
     * @return string[] lines
     */
    public static function compare(array $mine, array $theirs): array {
        $lines = [];
        $mine = self::flatten($mine);
        $theirs = self::flatten($theirs);
        foreach ($theirs as $path => $value) {
            if (!array_key_exists($path, $mine)) {
                $lines[] = get_string('compare_added', 'tool_wizards', ['path' => $path, 'value' => $value]);
            } else if ($mine[$path] !== $value) {
                $lines[] = get_string('compare_changed', 'tool_wizards', ['path' => $path, 'mine' => $mine[$path],
                    'theirs' => $value]);
            }
        }
        foreach ($mine as $path => $value) {
            if (!array_key_exists($path, $theirs)) {
                $lines[] = get_string('compare_removed', 'tool_wizards', ['path' => $path, 'value' => $value]);
            }
        }
        return $lines;
    }

    /**
     * A definition as path => value, with screens and questions named by their keys.
     *
     * @param mixed $value the value
     * @param string $path the path so far
     * @return array
     */
    protected static function flatten($value, string $path = ''): array {
        if (!is_array($value)) {
            return [$path => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
        }
        $out = [];
        foreach ($value as $k => $v) {
            $name = $k;
            if (is_int($k) && is_array($v)) {
                $name = $v['key'] ?? ($v['use'] ?? ($v['value'] ?? ($v['action'] ?? $k)));
            }
            $out += self::flatten($v, $path === '' ? (string) $name : $path . ' › ' . $name);
        }
        return $out;
    }
}
