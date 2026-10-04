# Wizards 0.1.1

First release. Supports Moodle 5.2 and 5.3, and can be installed with Composer
(`adamjenkins/moodle-tool_wizards`).

- A step-by-step course wizard that asks one question at a time: name, category, layout,
  sections, start date and visibility. A review screen comes before anything is created, and
  "Show all settings" leads to the standard course form with the answers filled in.
- First-content suggestions on new and nearly empty courses, with mini-wizards for a file,
  slides, a picture, a page, a forum, a glossary and a quiz. A quiz can come with a first
  multiple choice or true/false question.
- The mini-wizards ask a few questions at a time, one theme per screen. The first screen asks
  what the activity is for (picture cards for forum, glossary, quiz, file and slides), which
  fills in the later screens with settings that suit it. "Enough questions, let's go" creates it
  from there at any point. Later screens cover timing, tries, feedback and review for quizzes;
  emails, deadlines, grading and posting limits for forums; approval, look and linking for
  glossaries; and visibility, groups and completion for all.
- Suggestions can be hidden for one course, or switched off (and back on in Preferences).
  A course's hidden suggestions come back from "Bring the wizards back" in the page footer's
  help menu.
- Optional integration with Teacher scaffold: newly unlocked activities are announced on the
  teacher's next course page.
