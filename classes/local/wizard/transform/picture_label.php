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

namespace tool_wizards\local\wizard\transform;

/**
 * A picture shown in a text and media area: the picture, sized to fit, with its caption.
 *
 * Inputs: "file" (a file question), "alt" (what the picture shows), "caption" (optional).
 * The image points into the draft area; add_moduleinfo() saves the description's draft
 * files and rewrites the link, as the standard form's editor does.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class picture_label extends \tool_wizards\local\wizard\transform {
    /**
     * The inputs.
     *
     * @return string[]
     */
    public static function inputs(): array {
        return ['file', 'alt', 'caption'];
    }

    /**
     * The name and the text and media area.
     *
     * @param array $inputs name => answer
     * @return array
     */
    public static function apply(array $inputs): array {
        $draftitemid = (int) ($inputs['file'] ?? 0);
        $file = \tool_wizards\local\wizard\engine::first_draft_file($draftitemid, true);
        if (!$file) {
            return [];
        }
        $url = \core\url::make_draftfile_url($draftitemid, $file->get_filepath(), $file->get_filename());
        $alt = trim((string) ($inputs['alt'] ?? ''));
        $caption = trim((string) ($inputs['caption'] ?? ''));
        $img = \html_writer::empty_tag('img', ['src' => $url->out(false), 'alt' => $alt, 'class' => 'img-fluid']);
        $html = $caption === ''
            ? \html_writer::tag('p', $img)
            : \html_writer::tag('figure', $img . \html_writer::tag('figcaption', s($caption)), ['class' => 'figure']);
        return [
            'name' => $caption !== '' ? $caption : $alt,
            'introeditor' => ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => $draftitemid],
        ];
    }
}
