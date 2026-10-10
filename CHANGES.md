# Wizards 0.3.1

## Security

- A wizard's title is now always shown as plain text in the pop-up that opens the wizard. Before, HTML
  in a title (which only people who can manage wizards can write) was shown as HTML there.

## Fixed

- The "Added so far" list of the example work wizard no longer shows an "&" in a title as "&amp;".

## Changed

- New tests check the parts of core's forms that the wizards rely on, so a Moodle release that changes
  them fails the tests with a clear message.
- The README lists the plugin's capability (`tool/wizards:managewizards`), the permissions each kind of
  wizard needs, and the 38 wizards that come with the plugin.
- The camp listing shows the current first-content card and the admin list of wizards, and the question bank
  categories wizard.
