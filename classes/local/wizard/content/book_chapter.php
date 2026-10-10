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

namespace tool_wizards\local\wizard\content;

use tool_wizards\local\wizard\content;

/**
 * A chapter (or subchapter) added at the end of a book.
 *
 * Saved as mod/book/edit.php saves a new chapter (the "adding new chapter" branch): the row,
 * its files, the book's revision, the chapter_created event, then book_preload_chapters() to
 * fix the structure.
 *
 * @package    tool_wizards
 * @copyright  2004-2011 Petr Skoda {@link http://skodak.org}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_chapter extends content {
    /** @var int The longest title mod/book/edit_form.php accepts. */
    const MAXTITLE = 1333;

    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'book';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'title' => 'the chapter\'s title',
            'content' => 'the chapter\'s text (an editor value: text, format, itemid)',
            'subchapter' => '1 for a subchapter of the last chapter, 0 for a chapter',
        ];
    }

    /**
     * The book's own pages.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-book-view', 'mod-book-edit'];
    }

    /**
     * Editing a book's chapters needs mod/book:edit (mod/book/edit.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/book:edit'];
    }

    /**
     * A subchapter is offered only once the book has a chapter for it to belong to.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        global $DB;
        $haschapters = $DB->record_exists('book_chapters', ['bookid' => $cm->instance]);
        return ['subchapter' => $haschapters ? ['0', '1'] : ['0']];
    }

    /**
     * A title and some content are required, as in mod/book/edit_form.php.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $errors = [];
        $title = trim((string) ($fields['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = get_string('bookchapter_error_title', 'tool_wizards');
        } else if (\core_text::strlen($title) > self::MAXTITLE) {
            $errors['title'] = get_string('maximumchars', '', self::MAXTITLE);
        }
        $content = $fields['content'] ?? null;
        if (!is_array($content) || html_is_blank((string) ($content['text'] ?? ''))) {
            $errors['content'] = get_string('bookchapter_error_content', 'tool_wizards');
        }
        return $errors;
    }

    /**
     * Add the chapter at the end of the book.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = $cm->context;
        // The same editor options as mod/book/edit.php.
        $options = ['noclean' => true, 'subdirs' => true, 'maxfiles' => -1, 'maxbytes' => 0, 'context' => $context];

        $lastpage = (int) $DB->get_field('book_chapters', 'MAX(pagenum)', ['bookid' => $book->id]);
        $subchapter = $lastpage > 0 && !empty($fields['subchapter']) ? 1 : 0;
        $editor = $fields['content'];

        $data = new \stdClass();
        $data->bookid = $book->id;
        $data->pagenum = $lastpage + 1;
        $data->subchapter = $subchapter;
        $data->title = trim((string) $fields['title']);
        $data->hidden = 0;
        $data->timecreated = time();
        $data->timemodified = time();
        $data->importsrc = '';
        $data->content = '';
        $data->contentformat = FORMAT_HTML;
        // Nothing to make room for: the new page is after the last one, as edit.php's shift would leave it.
        $data->id = $DB->insert_record('book_chapters', $data);

        $data->content_editor = [
            'text' => (string) ($editor['text'] ?? ''),
            'format' => (int) ($editor['format'] ?? FORMAT_HTML),
            'itemid' => (int) ($editor['itemid'] ?? 0),
        ];
        $data = file_postupdate_standard_editor($data, 'content', $options, $context, 'mod_book', 'chapter', $data->id);
        unset($data->content_editor);
        $DB->update_record('book_chapters', $data);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        $chapter = $DB->get_record('book_chapters', ['id' => $data->id]);

        \mod_book\event\chapter_created::create_from_chapter($book, $context, $chapter)->trigger();

        book_preload_chapters($book);

        $string = $chapter->subchapter ? 'bookchapter_saved_sub' : 'bookchapter_saved';
        // Plain text: the wizard shows it with textContent.
        $title = format_string($chapter->title, true, ['context' => $context, 'escape' => false]);
        return get_string($string, 'tool_wizards', $title);
    }

    /**
     * The book itself.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/book/view.php', ['id' => $cm->id]);
    }
}
