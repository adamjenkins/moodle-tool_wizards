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

use tool_wizards\local\content_creator;
use tool_wizards\local\question_creator;
use tool_wizards\local\wizard\defaults;
use tool_wizards\local\wizard\engine;

/**
 * Tests for adding questions to a quiz and to a shared question bank.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_question::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(qbank_question::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_creator::class)]
final class question_test extends \advanced_testcase {
    /** @var \stdClass the course */
    protected \stdClass $course;

    /**
     * A course and a teacher (the current user).
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->setUser($generator->create_and_enrol($this->course, 'editingteacher'));
        self::reset_question_config();
    }

    /**
     * Forget question_bank's cached question settings (it keeps them for the whole run).
     */
    protected function tearDown(): void {
        self::reset_question_config();
        parent::tearDown();
    }

    /**
     * Forget question_bank's cached copy of the question settings.
     */
    protected static function reset_question_config(): void {
        (new \ReflectionProperty(\question_bank::class, 'questionconfig'))->setValue(null, null);
    }

    /**
     * An empty quiz.
     *
     * @return \cm_info
     */
    protected function make_quiz(): \cm_info {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'questionsperpage' => 0]);
        return get_fast_modinfo($this->course)->get_cm($quiz->cmid);
    }

    /**
     * The question a save just added, by its name.
     *
     * @param string $name the question name
     * @return \stdClass
     */
    protected function question(string $name): \stdClass {
        global $DB;
        return $DB->get_record('question', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * The fractions of a question's answers, answer => fraction.
     *
     * @param int $questionid the question
     * @return array
     */
    protected function fractions(int $questionid): array {
        global $DB;
        return array_column(
            array_values($DB->get_records('question_answers', ['question' => $questionid], 'id')),
            'fraction',
            'answer'
        );
    }

    /**
     * One question of each kind, saved from fields, all in the quiz's own bank and on the quiz.
     */
    public function test_quiz_each_kind(): void {
        global $DB;
        $cm = $this->make_quiz();
        $doc = defaults::shipped()['quizquestion'];
        $this->assertTrue(quiz_question::is_available($cm));
        $this->assertSame(question_creator::WIZARD_QTYPES, quiz_question::offered($cm)['qtype']);

        $added = content_creator::save($doc, $cm, [
            'qtype' => 'multichoice', 'single' => 1, 'questiontext' => 'Which planet is largest?',
            'choice' => [1 => 'Mars', 2 => 'Jupiter', 3 => '', 4 => 'Venus'], 'rightchoice' => 2,
            'feedbackright' => 'Well done', 'mark' => '2',
        ]);
        $this->assertSame(get_string('question_added', 'tool_wizards', 'Which planet is largest?'), $added);
        $q = $this->question('Which planet is largest?');
        $this->assertSame('multichoice', $q->qtype);
        $this->assertEquals(2, $q->defaultmark);
        $this->assertSame(['Mars' => '0.0000000', 'Jupiter' => '1.0000000', 'Venus' => '0.0000000'], $this->fractions($q->id));
        $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $q->id], '*', MUST_EXIST);
        $this->assertEquals(1, $options->single);
        $this->assertStringContainsString('Well done', $options->correctfeedback);
        $category = $DB->get_record('question_categories', ['id' => get_question_bank_entry($q->id)->questioncategoryid]);
        $this->assertEquals($cm->context->id, $category->contextid, 'In the quiz\'s own question bank.');

        content_creator::save($doc, $cm, [
            'qtype' => 'multichoice', 'single' => 0, 'name' => 'Even numbers',
            'questiontext' => ['text' => '<p>Which are even?</p>', 'format' => FORMAT_HTML],
            'choice' => [1 => '2', 2 => '3', 3 => '4', 4 => '5'], 'right' => [1 => 1, 2 => 0, 3 => 1, 4 => 0],
        ]);
        $q = $this->question('Even numbers');
        $this->assertSame(
            ['2' => '0.5000000', '3' => '-0.5000000', '4' => '0.5000000', '5' => '-0.5000000'],
            $this->fractions($q->id)
        );
        $this->assertEquals(0, $DB->get_field('qtype_multichoice_options', 'single', ['questionid' => $q->id]));

        content_creator::save($doc, $cm, ['qtype' => 'truefalse', 'questiontext' => 'The moon is a star.', 'correctanswer' => 0]);
        $q = $this->question('The moon is a star.');
        $options = $DB->get_record('question_truefalse', ['question' => $q->id], '*', MUST_EXIST);
        $this->assertSame('0.0000000', $DB->get_field('question_answers', 'fraction', ['id' => $options->trueanswer]));
        $this->assertSame('1.0000000', $DB->get_field('question_answers', 'fraction', ['id' => $options->falseanswer]));

        content_creator::save($doc, $cm, [
            'qtype' => 'shortanswer', 'questiontext' => 'Capital of France?',
            'accept' => [1 => 'Paris', 2 => '', 3 => 'Lutetia'], 'feedbackwrong' => 'Think of the Eiffel Tower.',
        ]);
        $q = $this->question('Capital of France?');
        $this->assertSame(['Paris' => '1.0000000', 'Lutetia' => '1.0000000', '*' => '0.0000000'], $this->fractions($q->id));
        $this->assertEquals(0, $DB->get_field('qtype_shortanswer_options', 'usecase', ['questionid' => $q->id]));

        content_creator::save($doc, $cm, [
            'qtype' => 'numerical', 'questiontext' => 'Half of seven?', 'number' => '3.5', 'tolerance' => '0.1',
        ]);
        $q = $this->question('Half of seven?');
        $this->assertSame(['3.5' => '1.0000000'], $this->fractions($q->id));
        $this->assertEquals(0.1, (float) $DB->get_field('question_numerical', 'tolerance', ['question' => $q->id]));

        content_creator::save($doc, $cm, [
            'qtype' => 'match', 'questiontext' => 'Match the capitals.',
            'item' => [1 => 'France', 2 => 'Japan'], 'match' => [1 => 'Paris', 2 => 'Tokyo', 3 => 'Rome'],
        ]);
        $q = $this->question('Match the capitals.');
        $subs = $DB->get_records('qtype_match_subquestions', ['questionid' => $q->id], 'id');
        $this->assertSame(['Paris', 'Tokyo', 'Rome'], array_values(array_column($subs, 'answertext')));
        $this->assertSame('', trim(strip_tags(end($subs)->questiontext)), 'The extra answer matches no item.');

        content_creator::save($doc, $cm, [
            'qtype' => 'essay', 'questiontext' => 'Describe your weekend.', 'responseformat' => 'plain',
            'responselines' => 5, 'maxwords' => '150', 'graderinfo' => 'Look for past tenses.',
        ]);
        $q = $this->question('Describe your weekend.');
        $options = $DB->get_record('qtype_essay_options', ['questionid' => $q->id], '*', MUST_EXIST);
        $this->assertSame('plain', $options->responseformat);
        $this->assertEquals(5, $options->responsefieldlines);
        $this->assertEquals(150, $options->maxwordlimit);
        $this->assertStringContainsString('past tenses', $options->graderinfo);

        content_creator::save($doc, $cm, ['qtype' => 'essay', 'questiontext' => 'Upload your poster.', 'responseformat' => 'file']);
        $options = $DB->get_record('qtype_essay_options', ['questionid' => $this->question('Upload your poster.')->id]);
        $this->assertSame('noinline', $options->responseformat);
        $this->assertEquals(1, $options->attachmentsrequired);

        content_creator::save($doc, $cm, [
            'qtype' => 'description', 'questiontext' => 'Read the passage below.', 'page' => 'new', 'mark' => '5',
        ]);
        $q = $this->question('Read the passage below.');
        $this->assertSame('description', $q->qtype);
        $this->assertEquals(0, $q->defaultmark);

        $structure = \mod_quiz\quiz_settings::create($cm->instance)->get_structure();
        $slots = array_values($structure->get_slots());
        $this->assertCount(9, $slots);
        $this->assertEquals(1, $slots[7]->page);
        $this->assertEquals(2, $slots[8]->page, 'The description went on a new page.');
        $this->assertEquals(2 + 1 + 1 + 1 + 1 + 1 + 1 + 1, $DB->get_field('quiz', 'sumgrades', ['id' => $cm->instance]));
        $this->assertDebuggingNotCalled();
    }

    /**
     * All six answer slots, with feedback: more answers than the question form shows at first (it builds them
     * from the request's "noanswers", as when a teacher asks for more blanks).
     */
    public function test_six_answers_with_feedback(): void {
        global $DB;
        $cm = $this->make_quiz();
        $doc = defaults::shipped()['quizquestion'];
        $choices = [];
        foreach (range(1, 6) as $n) {
            $choices[$n] = "Answer $n";
        }
        content_creator::save($doc, $cm, ['qtype' => 'multichoice', 'single' => 1, 'name' => 'Six answers',
            'questiontext' => 'Pick the fourth.', 'choice' => $choices, 'rightchoice' => 4,
            'feedbackright' => 'Yes', 'feedbackwrong' => 'No', 'mark' => '1']);
        $q = $this->question('Six answers');
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id');
        $this->assertCount(6, $answers);
        $this->assertSame(['Answer 4'], array_values(array_map(
            fn($a) => strip_tags($a->answer),
            array_filter($answers, fn($a) => (float) $a->fraction === 1.0)
        )));
    }

    /**
     * The wizard's answers become the handler's fields, and they save.
     */
    public function test_quiz_through_engine(): void {
        $cm = $this->make_quiz();
        $doc = defaults::shipped()['quizquestion'];
        $engine = (new engine($doc))->set_cm($cm);
        $answers = $engine->answers([
            'kind' => 'multiresponse',
            'questiontext' => ['text' => '<p>Which are fruit?</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'name' => '',
            'choice1' => 'Apple', 'choice2' => 'Carrot', 'choice3' => 'Pear', 'choice4' => '', 'choice5' => '', 'choice6' => '',
            'right1' => 1, 'right2' => 0, 'right3' => 1, 'right4' => 0, 'right5' => 0, 'right6' => 0,
            'mark' => '1', 'feedbackright' => '', 'feedbackwrong' => '',
            'page' => 'end',
        ]);
        $this->assertSame([], $engine->check($answers));
        $fields = $engine->fields($answers);
        $this->assertSame('multichoice', $fields['qtype']);
        $this->assertSame(0, $fields['single']);
        $this->assertSame([], content_creator::check($doc, $cm, $fields));
        content_creator::save($doc, $cm, $fields);

        $q = $this->question('Which are fruit?');
        $this->assertSame(['Apple' => '0.5000000', 'Carrot' => '-1.0000000', 'Pear' => '0.5000000'], $this->fractions($q->id));
        $this->assertCount(1, \mod_quiz\quiz_settings::create($cm->instance)->get_structure()->get_slots());
    }

    /**
     * Fields the handler refuses, and question types the site has switched off.
     */
    public function test_check_refuses(): void {
        $cm = $this->make_quiz();
        $mc = ['qtype' => 'multichoice', 'single' => 1, 'questiontext' => 'Q?', 'choice' => [1 => 'A', 2 => 'B']];
        $this->assertSame(
            ['rightchoice' => get_string('error_correctchoice', 'tool_wizards')],
            quiz_question::check($cm, $mc + ['rightchoice' => 3])
        );
        $this->assertSame(
            ['choice' => get_string('error_twochoices', 'tool_wizards')],
            quiz_question::check($cm, ['choice' => [1 => 'A'], 'rightchoice' => 1] + $mc)
        );
        $this->assertSame(
            ['right' => get_string('question_error_noright', 'tool_wizards')],
            quiz_question::check($cm, ['single' => 0, 'right' => [1 => 0, 2 => 0]] + $mc)
        );
        $this->assertArrayHasKey('match', quiz_question::check($cm, [
            'qtype' => 'match', 'questiontext' => 'Q?', 'item' => [1 => 'A', 2 => 'B'], 'match' => [1 => 'a', 2 => 'b'],
        ]));
        $this->assertArrayHasKey('number', quiz_question::check($cm, [
            'qtype' => 'numerical', 'questiontext' => 'Q?', 'number' => 'lots',
        ]));
        $this->assertArrayHasKey('page', quiz_question::check($cm, $mc + ['rightchoice' => 1, 'page' => 'middle']));

        set_config('essay_disabled', 1, 'question');
        self::reset_question_config();
        $this->assertNotContains('essay', quiz_question::offered($cm)['qtype']);
        $this->assertSame(
            ['qtype' => get_string('error_noquestion', 'tool_wizards')],
            quiz_question::check($cm, ['qtype' => 'essay', 'questiontext' => 'Q?'])
        );
        $this->expectException(\moodle_exception::class);
        content_creator::save(defaults::shipped()['quizquestion'], $cm, ['qtype' => 'essay', 'questiontext' => 'Q?']);
    }

    /**
     * A question in a shared question bank: in its default category, or a chosen one of its own.
     */
    public function test_qbank(): void {
        global $DB;
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course)->get_cm($qbank->cmid);
        $doc = defaults::shipped()['qbankquestion'];
        $this->assertTrue(qbank_question::is_available($cm));

        $added = content_creator::save($doc, $cm, [
            'qtype' => 'shortanswer', 'questiontext' => 'Chemical symbol for gold?', 'accept' => [1 => 'Au'],
            'casesensitive' => 1,
        ]);
        $this->assertSame(get_string('question_added', 'tool_wizards', 'Chemical symbol for gold?'), $added);
        $q = $this->question('Chemical symbol for gold?');
        $this->assertEquals(1, $DB->get_field('qtype_shortanswer_options', 'usecase', ['questionid' => $q->id]));
        $default = question_get_default_category($cm->context->id);
        $this->assertEquals($default->id, get_question_bank_entry($q->id)->questioncategoryid);
        $entryid = get_question_bank_entry($q->id)->id;
        $this->assertFalse($DB->record_exists('question_references', ['questionbankentryid' => $entryid]), 'Not used by any quiz.');

        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $cm->context->id, 'parent' => $default->id]);
        content_creator::save($doc, $cm, [
            'qtype' => 'truefalse', 'questiontext' => 'Gold is a metal.', 'correctanswer' => 1, 'category' => $category->id,
        ]);
        $this->assertEquals($category->id, get_question_bank_entry($this->question('Gold is a metal.')->id)->questioncategoryid);

        // A category of another bank is refused.
        $quizcm = $this->make_quiz();
        $other = question_get_default_category($quizcm->context->id, true);
        $this->assertSame(
            ['category' => get_string('question_error_category', 'tool_wizards')],
            qbank_question::check($cm, ['qtype' => 'truefalse', 'questiontext' => 'X', 'category' => $other->id])
        );
        $this->assertDebuggingNotCalled();
    }
}
