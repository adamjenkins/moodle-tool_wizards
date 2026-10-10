# Wizards (tool_wizards)

Friendly, step-by-step wizards that help teachers who are new to Moodle get a course
started, without long settings pages.

## What it does

### The course wizard

"Create a course with the wizard" asks one question at a time:

1. What do you want the course called? A short name is suggested, and you can change it.
2. Where should the course go? This is asked only if you can create courses in more than one category.
3. What layout do you want? Each layout comes with a plain description.
4. How many sections do you want to start with? Asked for layouts that have sections.
5. When does the course start? Asked only for weekly sections.
6. Should students see the course now? Asked only if you will be allowed to change this.

A review screen shows every answer, each with a "Change" button, before anything is created.
Everything else uses your site's usual defaults: exactly the values the standard "Add a new
course" form would use. "Show all settings", on every step, opens the standard form with your
answers filled in.

The course is created the same way the standard form creates it, and the person who created
it is enrolled in the same way. Events and defaults behave as usual.

The wizard is offered:

- in each course category's menu, and in the site home menu,
- in Site administration > Courses,
- next to Moodle's own "Add a new course" and "Create course" buttons. Admins can switch
  this off with the "Show wizard links next to "Add a new course"" setting.

### First content

On a new or nearly empty course, anyone who can add content there sees a card: "What would
you like to add first?" Each option opens a mini-wizard in a pop-up. The card shows the first
eight wizards; "More kinds of content" shows the rest. Every core activity and resource has a
wizard. The external tool wizard is offered only where the site or course has a tool set up.

A mini-wizard asks a few questions at a time, one theme per screen. The first screen asks the
essentials: a name and, for most kinds, **what it is for**, shown as picture cards. That answer
pre-fills the later screens with the settings that suit it. You can check each screen, or press
**"Enough questions, let's go"** at any point after the first to create it with what the
remaining screens already hold.

| Option | What it is for | Screens after the first |
|---|---|---|
| A file | Read it in the course / Download and keep it / Print it | details next to the link |
| Slides | Look through them in the course (PDF) / Download the original | details next to the link |
| A picture | (one screen: the picture, what it shows, a caption) | none |
| A page | (a title and the content) | shared screens only |
| A forum | Class discussion / Ask me questions / Answer before you see others / One discussion / Everyone posts once / Class blog | emails, deadline, grading, keeping discussions manageable |
| A glossary | Course vocabulary / Students build it together / Class FAQ / Collect useful links | approval, look, comments and repeated terms, automatic linking, grading |
| A quiz | Practice / Graded test / Pre-test / Homework | timing, tries, feedback, what students see afterwards, pass mark, layout, first question |
| An assignment | A file, such as an essay / Text written in Moodle / Work done outside Moodle / A group project | how work is handed in, dates, drafts and retries, marking, feedback, group work |
| A link | Read an article or web page / Watch a video / Use a website or tool | a note for students |
| A folder | Files on their own page / Files right on the course page | download button and how files open |
| A book | Course reading / Handbook / Collection of readings | chapter numbering, custom chapter headings |
| A choice | A class vote / A sign-up list / A quick check / Pick all that apply | answering, places, results, timing |
| A feedback survey | Course evaluation / Class opinion poll / Collecting information / Regular check-in | names, answering again, results, timing |
| A database | Shared collection / Share, then see others / Checked by you first / One entry each | approval, how many entries, open dates, comments and ratings |
| A wiki | Whole class together / Each group together / Each student alone | way of writing (editor or wiki markup) |
| A lesson | Tutorial / Choose your own path / Graded lesson | practice or graded, retakes, wrong answers, progress and page list, timing |
| A workshop | Feedback on each other's writing / Marking each other's projects / Reviewing classmates and themselves | what is handed in, how students review, marks, handing-in dates, review dates |
| An H5P activity | Practice / For a grade / Something to explore | tries and looking back, buttons under the activity |
| A SCORM package | A lesson to work through / A graded test | where it opens, tries, timing |
| A content package | (one screen: the package and a name) | shared screens only |
| An external tool | (one screen: one of the tools set up for the site or course, and a name) | shared screens only |
| A video meeting (BigBlueButton) | Live class with recordings / Live class only / Recordings only | waiting for a teacher and recording, opening times |
| A subsection | (one screen: a name) | visibility only |

After the activity's own screens come the shared ones, each shown only where it can matter:
visibility, groups (when the course has groups and does not force a group mode), and completion
(when the course tracks completion).

Choices are described by what students will experience; Moodle's own name for a setting is
mentioned where it helps you find it later. A purpose's preset replaces the site's default for
the settings it covers (for example, a practice quiz uses "Interactive with multiple tries").
Everything a wizard does not ask about is the activity's own default, as if you had used the
standard form and changed only those fields. Completion, the gradebook and events work as usual,
and a setting the site has switched off (a question behaviour, a way of showing files) is never
offered.

Only the kinds of content you are allowed to add in that course are offered. Afterwards the
card confirms what was added ("Nice, Week 1 discussion was added.") and suggests something
different to add next.

The card never blocks the page. You can:

- close it for now ("I'm done for now"),
- hide it for one course ("Hide for this course"),
- switch it off everywhere ("Don't show me wizard suggestions").

To undo either, open the help button (?) at the bottom of a course page and choose **"Bring back
the course wizards"** (or use Preferences > Wizard suggestions). The card shows again straight
away, even on a course that already has content.

### Teacher scaffold (optional)

If [Teacher scaffold](https://github.com/adamjenkins/moodle-tool_teacherscaffold)
(tool_teacherscaffold) is installed, a teacher who unlocks new activities sees a message on
their next course page, for example "Nice work! You've unlocked new activities: Quiz, Choice,
Feedback." The message has a button that opens the matching mini-wizard. Wizards does not
need Teacher scaffold, and works the same without it.

## In-activity wizards

These wizards add content to an activity that already exists, one item at a time, with
**"Add another"** after each save. Teachers find them as **"Add with a wizard"** (with the wand
icon) on the activity's own pages, and as buttons on the course page straight after an activity
wizard has added the activity.

| Wizard | Where | Kinds |
|---|---|---|
| Add quiz questions | the quiz's Questions page and question bank | pick one answer, tick all right answers, true/false, short answer, number, match pairs, essay, description |
| Add questions to the bank | a question bank's page | the same kinds |
| Add lesson pages | the lesson's Edit page | content page with buttons, the six question kinds, end of a branch; where right and wrong answers lead |
| Add chapters | the book | chapter or subchapter |
| Add entries | the glossary | term, definition, other words, linking |
| Add fields | the database's Fields page | text, long text, number, date, menu, radio buttons, checkboxes, URL, picture, file |
| Add feedback questions | the feedback's Questions page | multiple choice, short text, longer text, number, information |
| Add options | the choice | one more option, with its limit |
| Build the assessment form | the workshop | one criterion at a time, for the workshop's grading strategy |
| Give out reviews | the workshop | random allocation in plain language |
| Add example work | the workshop (when it uses examples) | an example submission |
| Move to the next stage | the workshop | where the workshop is and what happens next |

## Managing wizards

Every wizard, including the course wizard and the seven content wizards that come with the
plugin, is a **definition**: a JSON document that says what it creates, which screens and
questions it has, how answers turn into settings, and what each purpose pre-fills. Site
administrators (capability `tool/wizards:managewizards`) manage them in
*Site administration > Courses > Wizards > Manage wizards*:

- **Enable, disable and order** the wizards teachers see. Teachers also find them under
  **"Add with a wizard"** in each course section's add menu, in edit mode.
- **Edit** a wizard's wording in every installed language, the order of its screens, and its
  pictures (shipped ones, or your own PNG, JPEG, WebP, SVG or GIF uploads).
- **Try it**: walk any wizard in a real course and see exactly which settings it would set,
  without creating anything.
- **Build a new wizard** with the wizard builder. Choose what it creates (a course or any
  installed activity), tick the settings to ask about from that activity's own settings form,
  word the questions and group them into screens, and optionally add purpose cards with presets.
  New wizards start as drafts.
- **Duplicate, export and import** wizards, one at a time or in bulk, as a zip with their
  pictures. Imports are checked first; a wizard that already exists can be imported as a copy,
  replaced, or skipped.
- **Improve with AI**: if the site has a text-generation provider in Moodle's AI subsystem,
  describe a change in your own words and review the proposed differences before applying them.

The default wizards update with the plugin until you edit them. An edited default is flagged
when a newer version ships, with a comparison, **Reset to default** and **Keep my version**.

### Improving wizards with AI outside Moodle

**Download the AI runbook** from the wizard list. It explains the format, the design rules and
the round trip for working with any AI assistant on exported wizards, and it is included in
every export. The format is also described by `runbook/wizard.schema.json`, and files can be
checked before import with:

    php admin/tool/wizards/cli/validate.php wizards.zip

### Extending

Other plugins can add building blocks that definitions use: **actions**, which run after a wizard
has created its course or activity, and **transforms**, which turn several answers into settings.
Register them through the `\tool_wizards\hook\collect_extensions` hook.

## Settings

Site administration > Courses > Wizards:

- **Enable the wizards**: switches everything on or off for the whole site.
- **Suggest first content up to this many activities**: the card appears on courses with this
  many activities or fewer (default 1). The Announcements forum is not counted.
- **Show wizard links next to "Add a new course"**.

The plugin adds no capabilities. Each wizard needs the permission its action needs anyway:
`moodle/course:create` in the category for the course wizard, and
`moodle/course:manageactivities` plus the activity's own `mod/…:addinstance` for the first
content.

## Requirements

Moodle 5.2 or 5.3.

## Privacy

The plugin stores these for each user:

- whether they switched wizard suggestions off (a user preference),
- the courses where they hid the suggestions,
- messages waiting to be shown to them.

All three are covered by its privacy provider.

## Licence

GNU GPL v3 or later. See `LICENSE`.
