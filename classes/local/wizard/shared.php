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
 * The shared screens any activity wizard can include: {"use": "shared:visibility"},
 * {"use": "shared:groups"} and {"use": "shared:completion", "choices": [...]}.
 *
 * Each expands into an ordinary screen, shown only where it can matter.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shared {
    /**
     * Expand a shared screen.
     *
     * @param array $screen the {"use": …} screen
     * @param engine $engine the engine
     * @return array|null the screen, or null where it does not apply
     */
    public static function expand(array $screen, engine $engine): ?array {
        $s = fn($key) => ['string' => $key];
        $card = fn($value, $key) => ['value' => $value, 'title' => $s($key), 'desc' => $s($key . '_desc')];
        switch ($screen['use']) {
            case 'shared:visibility':
                return [
                    'key' => 'visibility',
                    'title' => $s('step_visibility'),
                    'question' => $s('step_visibility_q'),
                    'when' => self::combine(['capability' => 'moodle/course:activityvisibility'], $screen['when'] ?? null),
                    'items' => [[
                        'key' => 'visible', 'kind' => 'choice', 'label' => $s('step_visibility'), 'default' => 1,
                        'choices' => [$card(1, 'visible_show'), $card(0, 'visible_hide')],
                        'sets' => ['field' => 'visible', 'as' => 'int'],
                    ]],
                ];
            case 'shared:groups':
                if (!$engine->module_supports(FEATURE_GROUPS)) {
                    return null;
                }
                $when = ['all' => [['course' => 'hasgroups'], ['not' => ['course' => 'groupmodeforce']]]];
                return [
                    'key' => 'groups',
                    'title' => $s('step_groups'),
                    'question' => $s('step_groups_q'),
                    'when' => self::combine($when, $screen['when'] ?? null),
                    'items' => [[
                        'key' => 'groupmode', 'kind' => 'choice', 'label' => $s('step_groups'), 'default' => 'course:groupmode',
                        'choices' => [$card(NOGROUPS, 'groups_none'), $card(SEPARATEGROUPS, 'groups_separate'),
                            $card(VISIBLEGROUPS, 'groups_visible')],
                        'sets' => ['field' => 'groupmode', 'as' => 'int'],
                    ]],
                ];
            case 'shared:completion':
                $choices = [
                    ['value' => 'default', 'title' => $s('completion_default'), 'desc' => $s('completion_default_desc')],
                    ['value' => 'none', 'title' => $s('completion_none'), 'desc' => $s('completion_none_desc'),
                        'sets' => ['completion' => COMPLETION_TRACKING_NONE]],
                    ['value' => 'manual', 'title' => $s('completion_manual'), 'desc' => $s('completion_manual_desc'),
                        'sets' => ['completion' => COMPLETION_TRACKING_MANUAL]],
                ];
                foreach ($screen['choices'] ?? [] as $choice) {
                    $choice['sets'] = ['completion' => COMPLETION_TRACKING_AUTOMATIC] + ($choice['sets'] ?? []);
                    $choices[] = $choice;
                }
                return [
                    'key' => 'completion',
                    'title' => $s('step_completion'),
                    'question' => $s('step_completion_q'),
                    'when' => self::combine(['course' => 'completion'], $screen['when'] ?? null),
                    'items' => [[
                        'key' => 'completion', 'kind' => 'choice', 'label' => $s('step_completion'), 'default' => 'default',
                        'choices' => $choices,
                    ]],
                ];
        }
        return null;
    }

    /**
     * Both conditions.
     *
     * @param array $a a condition
     * @param array|null $b another, or null
     * @return array
     */
    protected static function combine(array $a, ?array $b): array {
        return $b === null ? $a : ['all' => [$a, $b]];
    }
}
