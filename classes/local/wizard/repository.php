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

use tool_wizards\local\module_creator;

/**
 * The stored wizards: one row per wizard in tool_wizards_wizard, its definition as JSON.
 *
 * Every write validates the definition first and clears the cache.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {
    /** @var string The table. */
    const TABLE = 'tool_wizards_wizard';

    /** @var int Not offered. */
    const STATUS_DISABLED = 0;

    /** @var int Offered to teachers. */
    const STATUS_ENABLED = 1;

    /** @var int Being built: only managers can try it. */
    const STATUS_DRAFT = 2;

    /** @var string A shipped default. */
    const ORIGIN_DEFAULT = 'default';

    /** @var string Built or duplicated here. */
    const ORIGIN_CUSTOM = 'custom';

    /** @var string Imported from a file. */
    const ORIGIN_IMPORTED = 'imported';

    /**
     * Every wizard, in order.
     *
     * @return \stdClass[] id => record
     */
    public static function all(): array {
        global $DB;
        $cache = \cache::make('tool_wizards', 'wizards');
        $all = $cache->get('all');
        if ($all === false) {
            $all = $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
            $cache->set('all', $all);
        }
        return $all;
    }

    /**
     * Clear the cache after a change.
     */
    public static function changed(): void {
        \cache::make('tool_wizards', 'wizards')->purge();
    }

    /**
     * A wizard by id.
     *
     * @param int $id the id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        return self::all()[$id] ?? null;
    }

    /**
     * A wizard by key.
     *
     * @param string $key the key
     * @return \stdClass|null
     */
    public static function get_by_key(string $key): ?\stdClass {
        foreach (self::all() as $record) {
            if ($record->wizardkey === $key) {
                return $record;
            }
        }
        return null;
    }

    /**
     * A record's definition.
     *
     * @param \stdClass $record the record
     * @return array
     */
    public static function definition(\stdClass $record): array {
        return json_decode($record->definition, true) ?: [];
    }

    /**
     * The enabled activity wizards a user may use in a course, in order.
     *
     * @param \stdClass $course the course
     * @param \stdClass|null $user the user, default the current one
     * @return \stdClass[]
     */
    public static function for_course(\stdClass $course, ?\stdClass $user = null): array {
        $out = [];
        foreach (self::all() as $record) {
            if (
                (int) $record->status === self::STATUS_ENABLED && $record->target !== 'course'
                    && module_creator::is_available($course, $record->target, $user)
                    && plugin_supports('mod', $record->target, FEATURE_CAN_DISPLAY, true)
            ) {
                $out[] = $record;
            }
        }
        return $out;
    }

    /**
     * The enabled wizards for activities that never show on the course page (question banks), which
     * a user may use in a course; offered on those activities' own course page instead.
     *
     * @param \stdClass $course the course
     * @param string $modname the module
     * @return \stdClass[]
     */
    public static function for_hidden_module(\stdClass $course, string $modname): array {
        return array_values(array_filter(self::all(), fn($record) => (int) $record->status === self::STATUS_ENABLED
            && $record->target === $modname && module_creator::is_available($course, $modname)));
    }

    /**
     * The enabled course wizards, in order.
     *
     * @return \stdClass[]
     */
    public static function course_wizards(): array {
        return array_values(array_filter(
            self::all(),
            fn($r) => (int) $r->status === self::STATUS_ENABLED && $r->target === 'course'
        ));
    }

    /**
     * What a definition's wizard is for, as stored: "course", the module name for an activity
     * wizard, or "content:<module name>" for an in-activity wizard.
     *
     * @param array $doc the definition
     * @return string
     */
    public static function target_of(array $doc): string {
        return match ($doc['target']['type'] ?? '') {
            'course' => 'course',
            'content' => 'content:' . ($doc['target']['modname'] ?? ''),
            default => (string) ($doc['target']['modname'] ?? ''),
        };
    }

    /**
     * Whether a record is an in-activity wizard.
     *
     * @param \stdClass $record the wizard
     * @return bool
     */
    public static function is_content(\stdClass $record): bool {
        return str_starts_with($record->target, 'content:');
    }

    /**
     * The module a record's wizard creates or adds to ('' for a course wizard).
     *
     * @param \stdClass $record the wizard
     * @return string
     */
    public static function modname_of(\stdClass $record): string {
        return $record->target === 'course' ? '' : preg_replace('/^content:/', '', $record->target);
    }

    /**
     * The enabled in-activity wizards a user may use on an activity, in order.
     *
     * @param \cm_info $cm the activity
     * @return \stdClass[]
     */
    public static function for_cm(\cm_info $cm): array {
        $out = [];
        foreach (self::all() as $record) {
            if (
                (int) $record->status === self::STATUS_ENABLED && $record->target === 'content:' . $cm->modname
                    && \tool_wizards\local\content_creator::may_use(self::definition($record), $cm)
            ) {
                $out[] = $record;
            }
        }
        return $out;
    }

    /**
     * Whether a user may run a wizard: enabled, or a draft and the user manages wizards.
     *
     * @param \stdClass $record the wizard
     * @return bool
     */
    public static function may_run(\stdClass $record): bool {
        $status = (int) $record->status;
        return $status === self::STATUS_ENABLED
            || has_capability('tool/wizards:managewizards', \core\context\system::instance());
    }

    /**
     * A canonical JSON encoding, for storage and for comparing versions.
     *
     * @param array $doc the definition
     * @return string
     */
    public static function encode(array $doc): string {
        return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The hash of a definition, for telling whether a default was edited.
     *
     * @param array $doc the definition
     * @return string
     */
    public static function hash(array $doc): string {
        return sha1(self::encode($doc));
    }

    /**
     * Save a definition: insert a new wizard, or update one.
     *
     * @param array $doc the definition
     * @param array $extra other fields for the record (status, origin, shippedversion, …)
     * @param int|null $id the wizard to update, or null to insert
     * @return int the id
     * @throws invalid_definition when the definition is not valid
     */
    public static function save(array $doc, array $extra = [], ?int $id = null): int {
        global $DB;
        $errors = validator::check($doc);
        if ($errors) {
            throw new invalid_definition($errors);
        }
        $existing = $DB->get_record(self::TABLE, ['wizardkey' => $doc['key']]);
        if ($existing && (int) $existing->id !== (int) $id) {
            throw new invalid_definition(['key: another wizard already uses "' . $doc['key'] . '"']);
        }
        $record = (object) $extra;
        $record->wizardkey = $doc['key'];
        $record->target = self::target_of($doc);
        $record->definition = self::encode($doc);
        $record->timemodified = time();
        if ($id) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
            self::changed();
            self::event('wizard_updated', $id, $doc['key']);
            return $id;
        }
        $record->status ??= self::STATUS_DRAFT;
        $record->origin ??= self::ORIGIN_CUSTOM;
        $record->timecreated = time();
        $record->sortorder = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '}') + 1;
        $id = $DB->insert_record(self::TABLE, $record);
        self::changed();
        self::event('wizard_created', $id, $doc['key']);
        return $id;
    }

    /**
     * Change a wizard's status.
     *
     * @param int $id the wizard
     * @param int $status a STATUS_ constant
     */
    public static function set_status(int $id, int $status): void {
        global $DB;
        $record = self::get($id);
        if (!$record || !in_array($status, [self::STATUS_DISABLED, self::STATUS_ENABLED, self::STATUS_DRAFT], true)) {
            return;
        }
        $DB->set_field(self::TABLE, 'status', $status, ['id' => $id]);
        self::changed();
        self::event('wizard_updated', $id, $record->wizardkey);
    }

    /**
     * Move a wizard up or down the order.
     *
     * @param int $id the wizard
     * @param int $direction -1 up, 1 down
     */
    public static function move(int $id, int $direction): void {
        global $DB;
        $ids = array_keys(self::all());
        $pos = array_search($id, $ids);
        $swap = $pos === false ? false : $pos + ($direction < 0 ? -1 : 1);
        if ($swap === false || $swap < 0 || $swap >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        foreach ($ids as $order => $wizardid) {
            $DB->set_field(self::TABLE, 'sortorder', $order + 1, ['id' => $wizardid]);
        }
        self::changed();
    }

    /**
     * Delete a wizard and its pictures. Defaults cannot be deleted unless no longer shipped.
     *
     * @param int $id the wizard
     * @return bool whether it was deleted
     */
    public static function delete(int $id): bool {
        global $DB;
        $record = self::get($id);
        if (!$record || ($record->origin === self::ORIGIN_DEFAULT && empty($record->retired))) {
            return false;
        }
        get_file_storage()->delete_area_files(\core\context\system::instance()->id, 'tool_wizards', 'picture', $id);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        self::changed();
        self::event('wizard_deleted', $id, $record->wizardkey);
        return true;
    }

    /**
     * A key not yet used, based on another.
     *
     * @param string $key the wanted key
     * @return string
     */
    public static function free_key(string $key): string {
        $base = substr(preg_replace('/_copy\d*$/', '', $key), 0, 50);
        if (!self::get_by_key($key)) {
            return $key;
        }
        for ($i = 1;; $i++) {
            $candidate = $base . '_copy' . ($i > 1 ? $i : '');
            if (!self::get_by_key($candidate)) {
                return $candidate;
            }
        }
    }

    /**
     * Duplicate a wizard as a custom draft, pictures included.
     *
     * @param int $id the wizard
     * @return int the new wizard's id
     */
    public static function duplicate(int $id): int {
        $record = self::get($id);
        $doc = self::definition($record);
        $doc['key'] = self::free_key($doc['key']);
        unset($doc['defaultversion']);
        $doc['title'] = text::with_language(
            $doc['title'],
            current_language(),
            get_string('copyof', 'tool_wizards', text::get($doc['title']))
        );
        $newid = self::save($doc, ['status' => self::STATUS_DRAFT, 'origin' => self::ORIGIN_CUSTOM]);
        $fs = get_file_storage();
        $context = \core\context\system::instance();
        foreach ($fs->get_area_files($context->id, 'tool_wizards', 'picture', $id, 'id', false) as $file) {
            $fs->create_file_from_storedfile(['itemid' => $newid], $file);
        }
        return $newid;
    }

    /**
     * Whether a default has been edited since it was installed.
     *
     * @param \stdClass $record the wizard
     * @return bool
     */
    public static function is_edited(\stdClass $record): bool {
        return $record->origin === self::ORIGIN_DEFAULT && $record->shippedhash !== null
            && $record->shippedhash !== self::hash(self::definition($record));
    }

    /**
     * Trigger an event about a wizard.
     *
     * @param string $name the event class name
     * @param int $id the wizard
     * @param string $key its key
     */
    protected static function event(string $name, int $id, string $key): void {
        $class = '\\tool_wizards\\event\\' . $name;
        $class::create(['context' => \core\context\system::instance(), 'objectid' => $id, 'other' => ['key' => $key]])
            ->trigger();
    }
}
