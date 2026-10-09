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
 * Wizard text: a language string reference, inline translations, or both.
 *
 * A text value in a definition is one of:
 * - a plain string (the same in every language);
 * - {"string": "key"} or {"string": "key", "component": "mod_quiz"}: from the language pack;
 * - {"en": "…", "ja": "…"}: inline translations;
 * - both at once: inline translations override the language pack for their languages.
 *
 * Resolution order: the user's language inline, the parent language inline, the language
 * pack reference, the site language inline, English inline, then any inline text.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text {
    /** @var string The component a reference without one belongs to. */
    const DEFAULT_COMPONENT = 'tool_wizards';

    /**
     * The text in the current language, as plain text (not escaped).
     *
     * @param mixed $text a text value
     * @param string|null $lang the language, default the current one
     * @return string
     */
    public static function get($text, ?string $lang = null): string {
        global $CFG;
        if ($text === null) {
            return '';
        }
        if (is_string($text)) {
            return $text;
        }
        if (!is_array($text)) {
            return (string) $text;
        }
        $lang = $lang ?? current_language();
        $inline = self::inline($text);
        if (isset($inline[$lang])) {
            return $inline[$lang];
        }
        $parent = get_parent_language($lang);
        if ($parent && isset($inline[$parent])) {
            return $inline[$parent];
        }
        if (isset($text['string']) && is_string($text['string'])) {
            $component = $text['component'] ?? self::DEFAULT_COMPONENT;
            $manager = get_string_manager();
            if ($manager->string_exists($text['string'], $component)) {
                return $manager->get_string($text['string'], $component, null, $lang);
            }
        }
        foreach ([$CFG->lang ?? 'en', 'en'] as $fallback) {
            if (isset($inline[$fallback])) {
                return $inline[$fallback];
            }
        }
        return $inline ? reset($inline) : '';
    }

    /**
     * The inline translations of a text value.
     *
     * @param mixed $text a text value
     * @return array language => text
     */
    public static function inline($text): array {
        if (!is_array($text)) {
            return [];
        }
        $out = [];
        foreach ($text as $lang => $value) {
            if ($lang !== 'string' && $lang !== 'component' && is_string($value)) {
                $out[$lang] = $value;
            }
        }
        return $out;
    }

    /**
     * Whether a key looks like a language code ("en", "ja", "pt_br", "zh_cn").
     *
     * @param string $key the key
     * @return bool
     */
    public static function is_language_key(string $key): bool {
        return (bool) preg_match('/^[a-z]{2,3}(_[a-z0-9]+)*$/', $key);
    }

    /**
     * Whether a text value is complete in a language: inline there, or from the language pack.
     *
     * @param mixed $text a text value
     * @param string $lang the language
     * @return bool
     */
    public static function has_language($text, string $lang): bool {
        if (is_string($text)) {
            return true;
        }
        if (!is_array($text)) {
            return false;
        }
        return isset(self::inline($text)[$lang]) || isset($text['string']);
    }

    /**
     * Set one language's inline text on a text value, keeping the rest.
     *
     * @param mixed $text a text value
     * @param string $lang the language
     * @param string $value the text; empty removes that language's inline text
     * @return mixed the new text value
     */
    public static function with_language($text, string $lang, string $value) {
        if (is_string($text)) {
            $text = $text === '' ? [] : ['en' => $text];
        }
        if (!is_array($text)) {
            $text = [];
        }
        if (trim($value) === '') {
            unset($text[$lang]);
        } else {
            $text[$lang] = $value;
        }
        return $text;
    }
}
