@tool @tool_wizards
Feature: Hide the first-content suggestions
  In order to work without suggestions I do not need
  As a teacher
  I need to hide them for one course, or switch them off and back on

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |

  @javascript
  Scenario: Hide the suggestions for one course only
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "Hide for this course" "button"
    Then I should not see "What would you like to add first?"
    When I reload the page
    Then I should not see "What would you like to add first?"
    When I am on "Course 2" course homepage
    Then I should see "What would you like to add first?"

  @javascript
  Scenario: Switch suggestions off everywhere, then back on from Preferences
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "Don't show me wizard suggestions" "button"
    Then I should not see "What would you like to add first?"
    When I am on "Course 2" course homepage
    Then I should not see "What would you like to add first?"
    When I follow "Preferences" in the user menu
    And I follow "Wizard suggestions"
    And I set the field "Wizard suggestions" to "1"
    And I press "Save changes"
    And I am on "Course 2" course homepage
    Then I should see "What would you like to add first?"

  @javascript
  Scenario: "I'm done for now" only closes the card
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    # The label's apostrophe defeats the named button locator, so the button is found by its action.
    When I click on "[data-region='tool_wizards-firstcontent'] .small [data-action='done']" "css_element"
    Then I should not see "What would you like to add first?"
    When I reload the page
    Then I should see "What would you like to add first?"
