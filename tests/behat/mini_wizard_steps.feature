@tool @tool_wizards @javascript
Feature: Mini-wizards ask a few questions at a time
  In order not to be overwhelmed by settings
  As a teacher
  I need each mini-wizard to ask the essentials first, cover one theme per screen, and let me stop early

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 3           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  Scenario: The essentials must be answered before going on
    When I click on "A quiz" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    Then "[data-wizard='go']" "css_element" should not be visible
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "Please answer the questions marked on this screen before going on."
    And I should see "What is this quiz for?"

  Scenario: A practice quiz walked screen by screen gets the practice settings
    When I click on "A quiz" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Quiz name" to "Warm-up"
    And I click on "input[name='purpose'][value='practice']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "When can students take it?"
    And "[data-wizard='go']" "css_element" should be visible
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "How many tries do students get?"
    And the field "Tries" matches value "As many as they like"
    When I click on "Back" "button" in the ".modal-dialog" "css_element"
    Then I should see "When can students take it?"
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "When do students find out if they are right?"
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "What can students see after they finish?"
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "Would you like to add a first question now?"
    When I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "Can students see it yet?"
    And "[data-wizard='go']" "css_element" should not be visible
    When I click on "Add" "button" in the ".modal-dialog form" "css_element"
    And I wait until the page is ready
    Then I should see "Nice, Warm-up was added."
    And the quiz "Warm-up" has "preferredbehaviour" set to "interactive"
    And the quiz "Warm-up" has "attempts" set to "0"

  Scenario: Changing the purpose re-fills only what the teacher has not changed
    When I click on "A quiz" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Quiz name" to "Test 1"
    And I click on "input[name='purpose'][value='exam']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then the field "Tries" matches value "1"
    When I click on "Back" "button" in the ".modal-dialog" "css_element"
    And I click on "Back" "button" in the ".modal-dialog" "css_element"
    And I click on "input[name='purpose'][value='practice']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then the field "Tries" matches value "As many as they like"
    When I set the field "Tries" to "2"
    And I click on "Back" "button" in the ".modal-dialog" "css_element"
    And I click on "Back" "button" in the ".modal-dialog" "css_element"
    And I click on "input[name='purpose'][value='exam']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then the field "Tries" matches value "2"
    When I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "Nice, Test 1 was added."
    And the quiz "Test 1" has "preferredbehaviour" set to "deferredfeedback"
    And the quiz "Test 1" has "attempts" set to "2"

  Scenario: Enough questions creates a Q and A forum from the essentials
    When I click on "A forum" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Forum name" to "Big question"
    And I click on "input[name='purpose'][value='qanda']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "Nice, Big question was added."
    And I am on the "Big question" "forum activity editing" page
    And the field "Forum type" matches value "Q and A forum"

  Scenario: A required answer is asked for before moving on
    When I click on "A forum" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Forum name" to "Our topic"
    And I click on "input[name='purpose'][value='single']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "Please answer the questions marked on this screen before going on."
    And I should see "What is this forum for?"

  Scenario: A problem the activity's own form finds opens the screen it belongs to
    When I click on "A forum" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Forum name" to "Deadline talk"
    And I click on "input[name='purpose'][value='general']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    Then I should see "Is there a deadline?"
    When I set the following fields to these values:
      | duedate[enabled]    | 1  |
      | duedate[day]        | 20 |
      | cutoffdate[enabled] | 1  |
      | cutoffdate[day]     | 10 |
    And I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "The cut-off date cannot be earlier than the due date."
    And I should see "Is there a deadline?"

  Scenario: A glossary for students to build together gets their settings
    When I click on "A glossary" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the field "Glossary name" to "Key terms"
    And I click on "input[name='purpose'][value='shared']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "Nice, Key terms was added."
    And I am on the "Key terms" "glossary activity editing" page
    And I expand all fieldsets
    And the field "Display format" matches value "Full with author"
    And the field "Approved by default" matches value "Yes"
