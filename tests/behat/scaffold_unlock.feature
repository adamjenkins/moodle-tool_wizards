@tool @tool_wizards @tool_wizards_scaffold
Feature: Hear about activities unlocked by Teacher scaffold
  In order to try new kinds of activity as I unlock them
  As a teacher tracked by Teacher scaffold
  I need a message on my next course page, with a way to try one straight away

  # Needs tool_teacherscaffold installed; the step skips the scenario otherwise (so it is skipped in CI).

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: The unlock message appears once and offers a mini-wizard
    Given Teacher scaffold reports that "teacher1" unlocked "quiz,choice,feedback"
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "Nice work! You've unlocked new activities: Quiz, Choice and Feedback."
    When I click on "Try Quiz now" "button"
    And I wait until the page is ready
    Then I should see "Add a quiz" in the ".modal-dialog" "css_element"
    When I click on "Cancel" "button" in the ".modal-dialog" "css_element"
    And I reload the page
    Then I should not see "You've unlocked new activities"
