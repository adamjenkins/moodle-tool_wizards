# Wizards 0.3.0

## Added

- Wizards for every core activity and resource: assignment, link, folder, book, choice, feedback,
  database, wiki, lesson, workshop, H5P, SCORM, IMS content package, external tool (one of the
  course's tools), BigBlueButton and subsection, and a question bank wizard on the course's
  Question banks page.
- In-activity wizards, which add content to an activity that already exists, one item at a time
  with "Add another": quiz and question bank questions (eight kinds), question bank categories
  (by chapter, unit, week, lesson, topics or your own word, with common subcategories in each),
  lesson pages, book chapters, glossary entries, database fields, feedback questions, choice
  options, and a workshop's assessment form, allocation of reviews, example work and phase.
  Teachers find them as "Add with a wizard" on the activity's own pages and straight after an
  activity wizard adds the activity. Plugins can add their own through the `collect_extensions`
  hook.
- The wizard list is grouped into course, activity and in-activity wizards, with an on/off switch
  per wizard, per group and per activity that saves without reloading the page.
- Import takes one JSON file holding many wizards, and "Export as one JSON file" writes them that
  way.
- File questions can take several files (used by the folder wizard).

## Changed

- The first-content card shows the first eight wizards and keeps the rest behind "More kinds of
  content".

## Fixed

- The file, slides and folder wizards opened no file picker.
- The H5P and SCORM wizards refused an uploaded package as "Required".
- A multiple-choice question with more than five answers failed to save.
- A hidden question's help line stayed visible.
- A question bank counted as course content, hiding the first-content card.
- "Bring back the course wizards" and "Hide for this course" now require the same add-content
  capability as every other way into the wizards.
