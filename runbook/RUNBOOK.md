# Improving Moodle wizards with AI — runbook

This runbook is for anyone who wants to change the wizards in the **Wizards** plugin
(`tool_wizards`) with the help of an AI assistant such as Claude: rewording questions,
translating them, adding a screen, or building a wizard for an activity that has none yet.

It is written for two readers: **you**, the site administrator, and **the AI** you give it to.

## 1. The round trip

1. In Moodle, go to *Site administration › Courses › Wizards › Manage wizards*.
2. Tick the wizards you want to work on, choose **Export selected**, and download the zip.
   (Or use **Export** on one wizard.) The zip contains each wizard's `wizard.json`, its
   pictures, this runbook, and `wizard.schema.json`. **Export as one JSON file** gives the
   same wizards in a single file instead (without uploaded pictures):
   `{"format": "tool_wizards/wizards@1", "wizards": [ … ]}`.
3. Give the AI the zip (or the `wizard.json` files), this runbook, and what you want changed.
   Good requests are concrete: *"Add Japanese to every text"*, *"Add a screen to the quiz
   wizard about timing for exams, with plain-language choices"*, *"Make the forum wizard's
   cards shorter and friendlier for primary school teachers"*.
4. Ask the AI to return the changed `wizard.json` files, one JSON file holding them all (the
   format above, or simply a JSON list of wizards), or a zip in the same layout.
5. In Moodle, **Import wizards**, upload the file, and choose what to do with wizards that
   already exist: *import as a copy* (safest), *replace*, or *skip*.
6. Imported wizards arrive as **drafts**. Use **Try it** to walk through them: nothing is
   created, and you see exactly which settings would be set. When you are happy, **Enable** them.

Every file is checked on import. A file with a mistake is reported with the exact place of
the problem (for example `screens[2].items[0].sets.field: must be a form field name`) and
nothing from it is saved. You can also check files before importing:

    php admin/tool/wizards/cli/validate.php path/to/wizard.json (or a zip)

If your site has Moodle's AI subsystem set up, you can skip the download: open a wizard's
**Edit** page and use **Improve with AI**. You see the proposed differences before anything
is saved.

## 2. What a wizard is

A wizard asks a teacher a few plain-language questions, **one theme per screen**, and then
creates a course or an activity with the answers. Every answer is passed to the activity's
own Moodle settings form, exactly as if the teacher had filled that form in, so Moodle's own
validation and defaults still apply. Anything the wizard does not ask about keeps Moodle's
default.

The first screen asks the essentials, usually a name and **what the activity is for**
(picture cards). Choosing a purpose **pre-fills the later screens** with settings that suit
it. From the second screen on, the teacher can press **"Enough questions, let's go"** and
accept everything that is already filled in.

### Design rules (please follow them when you change a wizard)

- **One theme per screen, at most about three decisions.** Split long screens.
- **The screen's heading is a question** a new teacher understands: "When can students take
  it?", not "Timing settings".
- **Describe choices by what students will experience**, not by Moodle's setting name.
  Mention Moodle's own term in the explanation if it helps the teacher find it later.
- **Turn confusing grids into a few named patterns** (the quiz's "what students see
  afterwards" is the example: four patterns instead of 32 checkboxes).
- **Essentials first.** The first screen only asks what must be asked.
- **Presets make "let's go" safe.** If a purpose implies a setting, put it in that
  purpose's `preset`.
- **Keep it short and kind.** Short sentences, no jargon, no exclamation marks.

## 3. The file format

A wizard is one JSON object. `wizard.schema.json` is the formal description; this section
explains it.

```json
{
  "format": "tool_wizards/wizard@1",
  "key": "quiz",
  "target": {"type": "module", "modname": "quiz"},
  "title": {"string": "type_quiz"},
  "heading": {"en": "Add a quiz"},
  "description": {"en": "Check what students know."},
  "intro": {"en": "Shown at the top of the first screen."},
  "screens": [ … ],
  "actions": [ … ]
}
```

| Property | Meaning |
|---|---|
| `format` | Always `"tool_wizards/wizard@1"`. |
| `key` | Unique id: lower-case letters, digits and `_`. **Do not change it** when improving a wizard; a new key makes a new wizard. |
| `defaultversion` | Only in wizards shipped with the plugin. Leave it alone. |
| `target` | `{"type": "course"}` for a course wizard, `{"type": "module", "modname": "forum"}` for an activity, or `{"type": "content", "modname": "lesson", "content": "tool_wizards/lesson_page"}` for an in-activity wizard (see §3.8). |
| `title` | The name teachers see in the list of wizards. Required. |
| `heading` | The pop-up's title, e.g. "Add a forum". |
| `description` | One line under the title in the list. |
| `intro` | A short paragraph at the top of the first screen. |
| `picture` | A picture for the wizard in the admin list. |
| `screens` | The screens, in order. The first one is the essentials. |
| `actions` | Code that runs after creation (see §3.7). |
| `notes` | Free text for administrators; not shown to teachers. |

### 3.1 Text and languages

Any text can be:

- a plain string: `"Practice"` — the same in every language;
- **inline translations**: `{"en": "Practice", "ja": "練習", "es": "Práctica"}`;
- a **language pack reference**: `{"string": "quiz_purpose_practice"}` (from the plugin's
  language file, translated by Moodle's language packs) or
  `{"string": "true", "component": "qtype_truefalse"}`;
- both: `{"string": "quiz_purpose_practice", "fr": "Entraînement"}` — the inline French
  replaces the language pack's French only.

Teachers see their own language, then the site's, then English. **To translate a wizard**,
add the language code to every text object, keeping `"string"` references as they are.
Language codes are Moodle's: `en`, `ja`, `es`, `pt_br`, `zh_cn`, … .

### 3.2 Screens

```json
{"key": "timing", "title": {"en": "Timing"}, "question": {"en": "When can students take it?"},
 "when": { … condition … }, "items": [ … questions … ]}
```

`title` is the short theme name in the progress bar; `question` is the screen's heading.
`when` (optional) shows the screen only when the condition holds. A screen whose questions
are all hidden is skipped.

**Shared screens** (activity wizards only) add Moodle's common settings, each only where it
matters:

```json
{"use": "shared:visibility"}
{"use": "shared:groups"}
{"use": "shared:completion", "choices": [
   {"value": "posts", "title": {"en": "When students post"}, "desc": {"en": "…"},
    "sets": {"completionpostsenabled": 1, "completionposts": 1}}]}
```

The completion screen always offers "the course's usual setting", "don't track it" and
"students tick it off themselves"; `choices` adds automatic rules (the wizard adds
`completion = 2` for them).

### 3.3 Questions

```json
{"key": "attempts", "kind": "select", "label": {"en": "Tries"}, "help": {"en": "…"},
 "default": "config:quiz/attempts", "required": true,
 "choices": [{"value": 1, "title": "1"}, {"value": 0, "title": {"en": "As many as they like"}}],
 "sets": {"field": "attempts"}}
```

| Kind | What the teacher sees | Notes |
|---|---|---|
| `cards` | Choices as cards with pictures | Use for purposes. Each choice may have `picture`. |
| `choice` | Choices as cards without pictures | For 2–5 options with explanations. |
| `select` | A drop-down | For long lists. |
| `yesno` | A checkbox | `label` (and optional `text` for the checkbox itself). Answer is 1 or 0. |
| `text`, `textarea` | A text box | `maxlength`, `placeholder` |
| `number`, `percent` | A number box | `min`, `max` (percent is 0–100 by default) |
| `date` | Date and time | `optional` (default true: "Enable" checkbox) |
| `duration` | A length of time | `units`: list of seconds per unit, e.g. `[60, 3600]` |
| `file` | A file upload | `accept`: `"*"`, `[".pdf", ".pptx"]` or `["web_image"]`; `maxfiles` (default 1, `-1` for no limit); `extensions` rule |
| `editor` | A rich text editor | For page content |
| `hint` | A line of explanation | `text` only, no answer |
| `category`, `shortname`, `review` | Course wizards only | course category, short name with suggestions, review screen |

Common properties: `key` (unique in the wizard), `label` (required), `help`, `required`
(`true` or a condition), `requiredmessage`, `default`, `when`.

Instead of `choices`, a choice question can take its choices from the site with `choicesfrom`:
`"course:formats"` (the course formats, course wizards only) or `"course:ltitools"` (the external
tools set up for the site and the course, external tool wizards only; set the answer to the
field `typeid`). In-activity wizards of a question bank or a quiz can use `"cm:questioncategories"`
(the bank's categories, for where a question goes) or `"cm:questioncategoryparents"` (the same with
the bank's top level first, for where new categories go).

Defaults may be a value, `"config:plugin/setting"` (the site's own default, e.g.
`"config:quiz/attempts"`), or `"course:groupmode"`.

### 3.4 Choices

```json
{"value": "practice", "title": {"en": "Practice"}, "desc": {"en": "Students try again as often as they like."},
 "picture": "pix:purpose/quiz_practice",
 "when": {"offered": {"field": "preferredbehaviour", "value": "interactive"}},
 "sets": {"preferredbehaviour": "interactive"},
 "preset": {"attempts": 0, "reviewpattern": "everything"}}
```

- `value` must be unique within the question.
- `sets`: the activity form fields this choice sets (see §3.5).
- `preset`: answers to fill in on later questions when this choice is picked. Keys are
  question keys; values must be valid answers for those questions. The teacher can change them.
- `picture`: `"pix:purpose/<name>"` (pictures shipped with the plugin) or
  `"file:<name>.png"` (a picture in the wizard's `pictures/` folder: png, jpg, webp, svg or
  gif, at most 2 MB).

### 3.5 Setting the activity's fields

A question's `sets` passes its answer to the activity form:

| Mapping | Meaning |
|---|---|
| `{"field": "name"}` | The answer becomes field `name`. Dates and durations are converted to the form's own format. |
| `{"field": "introeditor", "as": "editor"}` | Plain text becomes an editor field (a description). `as` can also be `int`, `float`, `string`. |
| `{"field": "name", "emptyfrom": {"filename": "files"}}` | If left empty, use the uploaded file's name. |
| `{"field": "gradepass", "percentof": "config:quiz/maximumgrade"}` | A percentage of a number. |
| `{"transform": "tool_wizards/picture_label", "from": {"file": "picture", "alt": "alt", "caption": "caption"}}` | Code that builds fields from several answers. |
| a list of these, each with an optional `when` | Several mappings. |

A **choice's** `sets` sets fixed values: `{"type": "qanda"}`. Nested fields are objects:
`{"grade_forum": {"modgrade_type": "point", "modgrade_point": "{answer:maxgrade}"}}` —
`"{answer:<key>}"` inserts another question's answer.

**Field names** are the names in the activity's settings form (Moodle's `mod_form.php`),
e.g. `name`, `introeditor`, `timeopen`, `attempts`, `forcesubscribe`. If you are not sure a
field exists, say so rather than guessing: an unknown field is ignored by Moodle, and a
wrong value is refused when the teacher submits. Use **Try it** to see what a wizard sets.

### 3.6 Conditions (`when`)

| Condition | True when |
|---|---|
| `{"answer": "purpose", "is": "exam"}` | the answer is "exam" (also `"not"`, `"in": [ … ]`, `"empty": true`) |
| `{"answer": "format", "has": "usessections"}` | the chosen course format has that flag (course wizards) |
| `{"course": "hasgroups"}` | the course has groups (also `completion`, `groupmodeforce`, `{"course": "forcedgroupmode", "is": 1}`) |
| `{"site": "comments"}` | site-wide comments are on (also `completion`, `showstartedcourses`, `categorychoice` — the user can create courses in more than one category —, `{"site": "filter", "name": "glossary"}`, `{"site": "qtype", "name": "truefalse"}`) |
| `{"capability": "moodle/course:activityvisibility"}` | the teacher has the capability (or a list of them) |
| `{"offered": {"field": "display", "value": 1}}` | the activity form offers that value (the site has not switched it off) |
| `{"all": [ … ]}`, `{"any": [ … ]}`, `{"not": { … }}` | combinations |

### 3.7 Actions

Code that runs after the wizard has created the course or activity. The plugin provides
`tool_wizards/quiz_first_question`; other plugins can add more. Leave existing actions as
they are unless asked; do not invent action names.

### 3.8 In-activity wizards

An in-activity wizard adds content to an activity that already exists: a question to a quiz,
a page to a lesson, a chapter to a book. Teachers find it on the activity's own pages, and
straight after creating the activity. After each save they can add another.

Its `target` names a **content handler**: code that saves that kind of content through the
activity's own API. Its questions' `sets` fill in the handler's fields, not a settings form,
and only the handler's fields are allowed; the file check lists them when one is wrong.
In-activity wizards have no shared screens.

## 4. Instructions for the AI

You are editing wizard definition files for the Moodle plugin `tool_wizards`.

1. **Return complete, valid JSON**: the whole `wizard.json`, not a fragment or a diff.
   No comments in JSON.
2. **Keep `format`, `key`, `defaultversion`, `target` and `actions` unchanged** unless the
   administrator explicitly asks to change them.
3. **Use only the building blocks in §3.** Unknown properties, kinds, conditions,
   transforms or actions make the file fail validation.
4. **Keep question keys stable**: presets, conditions and actions refer to them. If you
   rename one, update every reference.
5. **Keep `{"string": …}` references** when translating; add the language as a sibling key.
   Translate naturally for teachers, not word by word.
6. **Follow the design rules in §2**: one theme per screen, questions as headings, choices
   described by what students experience.
7. **Do not invent form fields or values.** If a request needs a field you are not sure of,
   say so in your answer outside the JSON and leave it out.
8. When you add a picture reference `file:<name>`, provide that picture file too, or use
   one of the shipped pictures (`pix:purpose/…` — see the existing wizards).
9. If anything is unclear, ask the administrator before changing the file.

## 5. Checklist before importing

- [ ] The file validates (`cli/validate.php`, or import as a copy and read the report).
- [ ] Every screen asks one theme; the first screen asks only the essentials.
- [ ] Every text exists in the languages your teachers use (the manage page lists them).
- [ ] Each purpose's preset matches what its card promises.
- [ ] **Try it** in a real course: walk every purpose, press "let's go" early, and read
      what would be set.
