# Wizards 0.3.2

## Added

- "Add with a wizard" is now in the menu of every add button on the course page in edit mode,
  including the "+" between two activities. A wizard started there adds the new activity at that
  place, not at the end of the section (GitHub issue #1).
- A new setting, "Suggest first content only in edit mode" (Site administration > Courses >
  Wizards > Settings, off by default). With it on, the "Ready to add some content?" card shows only
  while edit mode is on, so it stays out of the way when the course is shown to a class. It still
  shows straight after the course wizard creates a course, after a wizard adds something, and after
  "Bring back the course wizards" (GitHub issue #2).
- A plugin icon on the plugins overview page.

## Changed

- The "Add with a wizard" entries are added through Moodle's own hook for the course page's add
  buttons, instead of by a script that placed links on the page.
