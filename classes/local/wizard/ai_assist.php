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
 * "Improve with AI": edit a wizard definition through Moodle's AI subsystem (core_ai).
 *
 * The admin's instruction, the wizard's current definition and the AI prompt in
 * runbook/AI-PROMPT.md go to the site's text generation provider. The reply's JSON is
 * validated like any import; nothing is saved until the admin has seen the differences and
 * chosen to apply them.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_assist {
    /**
     * Whether the site can generate text with AI.
     *
     * @return bool
     */
    public static function available(): bool {
        if (!class_exists(\core_ai\manager::class) || !class_exists(\core_ai\aiactions\generate_text::class)) {
            return false;
        }
        try {
            return \core\di::get(\core_ai\manager::class)->is_action_available(\core_ai\aiactions\generate_text::class);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether the user has accepted the AI usage policy.
     *
     * @param int $userid the user
     * @return bool
     */
    public static function policy_accepted(int $userid): bool {
        return (bool) \core_ai\manager::get_user_policy_status($userid);
    }

    /**
     * Record that the user accepted the AI usage policy.
     *
     * @param int $userid the user
     */
    public static function accept_policy(int $userid): void {
        \core_ai\manager::user_policy_accepted($userid, \core\context\system::instance()->id);
    }

    /**
     * The prompt for one request.
     *
     * @param array $doc the current definition
     * @param string $instruction what the admin wants changed
     * @return string
     */
    public static function prompt(array $doc, string $instruction): string {
        global $CFG;
        $guide = (string) file_get_contents($CFG->dirroot . '/admin/tool/wizards/runbook/AI-PROMPT.md');
        $fence = str_repeat(chr(96), 3);
        $languages = implode(', ', array_keys(get_string_manager()->get_list_of_translations()));
        // The definition names language strings by key; show the wording teachers actually see.
        $wording = '';
        foreach (texts::all($doc) as $entry) {
            $wording .= '- ' . implode('.', $entry['path']) . ': ' . text::get($entry['text'], 'en') . "\n";
        }
        return $guide
            . "\n\n## This site\n\nInstalled languages: " . $languages . "\n"
            . "\n## The wizard definition to change\n\n" . $fence . "json\n" . repository::encode($doc) . "\n" . $fence . "\n"
            . "\n## What each text says now, in English (paths into the definition)\n\n" . $wording
            . "\n## The change the site administrator asks for\n\n" . trim($instruction) . "\n"
            . "\n## Your answer\n\nReply with the complete changed definition as one JSON object and nothing else.";
    }

    /**
     * Ask the AI for a changed definition.
     *
     * @param array $doc the current definition
     * @param string $instruction what to change
     * @param int $userid the user asking
     * @return array ['doc' => ?array, 'problems' => string[], 'error' => ?string]
     */
    public static function improve(array $doc, string $instruction, int $userid): array {
        $action = new \core_ai\aiactions\generate_text(
            \core\context\system::instance()->id,
            $userid,
            self::prompt($doc, $instruction)
        );
        $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        if (!$response->get_success()) {
            return ['doc' => null, 'problems' => [], 'error' => $response->get_errormessage() ?: $response->get_error()];
        }
        $content = (string) ($response->get_response_data()['generatedcontent'] ?? '');
        $proposed = self::extract_json($content);
        if ($proposed === null) {
            return ['doc' => null, 'problems' => [], 'error' => get_string('ai_nojson', 'tool_wizards')];
        }
        // The key identifies the wizard; the AI is not allowed to change it.
        $proposed['key'] = $doc['key'];
        if (isset($doc['defaultversion'])) {
            $proposed['defaultversion'] = $doc['defaultversion'];
        }
        return ['doc' => $proposed, 'problems' => validator::check($proposed), 'error' => null];
    }

    /**
     * The JSON object in a reply: the whole reply, a fenced block, or from the first { to the last }.
     *
     * @param string $content the reply
     * @return array|null
     */
    public static function extract_json(string $content): ?array {
        $candidates = [$content];
        $fence = str_repeat(chr(96), 3);
        if (preg_match('/' . $fence . '(?:json)?\s*(\{.*\})\s*' . $fence . '/s', $content, $m)) {
            $candidates[] = $m[1];
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end > $start) {
            $candidates[] = substr($content, $start, $end - $start + 1);
        }
        foreach ($candidates as $candidate) {
            $decoded = json_decode(trim($candidate), true);
            if (is_array($decoded) && !array_is_list($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
