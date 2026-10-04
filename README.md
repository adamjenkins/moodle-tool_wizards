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
you like to add first?" Each option opens a mini-wizard in a pop-up.

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
- hide it for one course ("Hide for this course"). To undo that, open the help button (?) at
  the bottom of that course page and choose "Bring the wizards back". The link appears only
  while the course is still nearly empty, since the card wouldn't show otherwise,
- switch it off everywhere ("Don't show me wizard suggestions"). You can switch it back on in
  Preferences > Wizard suggestions.

### Teacher scaffold (optional)

If [Teacher scaffold](https://github.com/adamjenkins/moodle-tool_teacherscaffold)
(tool_teacherscaffold) is installed, a teacher who unlocks new activities sees a message on
their next course page, for example "Nice work! You've unlocked new activities: Quiz, Choice,
Feedback." The message has a button that opens the matching mini-wizard. Wizards does not
need Teacher scaffold, and works the same without it.

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
