# Wizards 0.2.0

The wizards are now definitions that site administrators can manage, build and improve.

- *Site administration > Courses > Wizards > Manage wizards*: enable or disable each wizard, set
  their order, edit their wording in every installed language, choose or upload card pictures,
  duplicate, delete, and export or import them (one or many, as a zip with their pictures), with
  bulk actions.
- A wizard for building wizards: choose what it creates, pick that form's own settings, word the
  questions, group them into screens, and add purpose cards with presets.
- "Try it" walks any wizard in a real course and shows what it would set, without creating anything.
- "Improve with AI" in the wizard editor uses Moodle's AI subsystem when a text provider is set up.
  The proposed changes are shown before anything is saved.
- An AI runbook, a JSON Schema and a command-line validator (`cli/validate.php`) for improving
  exported wizards with any AI assistant.
- "Add with a wizard", with a magic wand icon, in every course section's add menu in edit mode,
  opening a list of the wizards with each activity's icon.
- Plugins can add their own actions and transforms through the
  `\tool_wizards\hook\collect_extensions` hook.
- The default wizards update with the plugin unless edited; edited ones are flagged when a newer
  default ships, with a comparison, "Reset to default" and "Keep my version".
- The course wizard's suggested short name no longer ends with the year, as courses are often
  reused from year to year.
- "Bring back the course wizards" in the footer's help menu appears whenever the wizards were
  hidden on that course or switched off everywhere, and shows the card again straight away.
