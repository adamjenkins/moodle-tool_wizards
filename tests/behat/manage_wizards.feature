@tool @tool_wizards
Feature: Manage the wizards
  In order to offer teachers wizards that suit my institution
  As a site administrator
  I need to switch wizards on and off, change their wording, build new ones and try them

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

  Scenario: The default wizards are listed, enabled
    Given I log in as "admin"
    When I navigate to "Courses > Wizards > Manage wizards" in site administration
    Then I should see "A forum"
    And I should see "A quiz"
    And I should see "Course wizard"
    And I should see "Enabled" in the "A forum" "table_row"
    And I should see "Default" in the "A forum" "table_row"

  Scenario: A disabled wizard is no longer offered to teachers
    Given I log in as "admin"
    And I navigate to "Courses > Wizards > Manage wizards" in site administration
    When I click on "Disable" "link" in the "A forum" "table_row"
    Then I should see "Disabled" in the "A forum" "table_row"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "What would you like to add first?"
    And I should not see "A forum" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I should see "A quiz" in the "[data-region='tool_wizards-firstcontent']" "css_element"

  Scenario: Changing a wizard's wording reaches teachers, and marks the default as edited
    Given I log in as "admin"
    And I navigate to "Courses > Wizards > Manage wizards" in site administration
    When I click on "Edit" "link" in the "A quiz" "table_row"
    And I set the field "title [en]" to "A practice quiz"
    And I press "Save changes"
    Then I should see "A practice quiz"
    And I should see "Default (edited)" in the "A practice quiz" "table_row"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should see "A practice quiz" in the "[data-region='tool_wizards-firstcontent']" "css_element"

  Scenario: An edited default can be compared with the shipped version and reset
    Given I log in as "admin"
    And I navigate to "Courses > Wizards > Manage wizards" in site administration
    And I click on "Edit" "link" in the "A glossary" "table_row"
    And I set the field "title [en]" to "Word list"
    And I press "Save changes"
    When I click on "Compare with default" "link" in the "Word list" "table_row"
    Then I should see "Removed: title › en = Word list"
    When I press "Reset to default"
    Then I should see "The wizard was reset to the default."
    And I should see "Default" in the "A glossary" "table_row"
    And I should not see "Word list"

  @javascript
  Scenario: Build a new wizard with the wizard builder, then try it
    Given I log in as "admin"
    And I navigate to "Courses > Wizards > Manage wizards" in site administration
    When I follow "Build a new wizard"
    And I set the field "What does it create?" to "Page"
    And I set the field "Name of the wizard" to "A reading page"
    And I press "Next"
    Then I should see "Tick the settings the wizard should ask about."
    And I press "Next"
    Then I should see "Word each question the way a new teacher would understand it"
    And I press "Next"
    Then I should see "what can the activity be for?"
    When I press "Save as a draft"
    Then I should see "Your wizard was saved as a draft."
    And I navigate to "Courses > Wizards > Manage wizards" in site administration
    And I should see "Draft" in the "A reading page" "table_row"
    When I click on "Try it" "link" in the "A reading page" "table_row"
    And I set the field "Course to try it in" to "Course 1"
    And I press "Try it"
    And I wait until the page is ready
    Then I should see "Trying the wizard: nothing will be created."
    When I set the field "Name" to "Chapter one"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I set the field "Page content" to "Once upon a time"
    And I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "name = Chapter one"
    And I should not see "Chapter one" in the "region-main" "region"

  @javascript
  Scenario: Teachers add with a wizard in any section, in edit mode
    Given the following "activities" exist:
      | activity | course | name   |
      | page     | C1     | Page 1 |
      | page     | C1     | Page 2 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Then I should not see "What would you like to add first?"
    When I click on "[data-action='open-addingcontent']:not([data-beforemod])" "css_element" in the "#section-2" "css_element"
    And I click on "Add with a wizard" "button" in the "#section-2" "css_element"
    And I click on "A forum" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    And I set the field "Forum name" to "Questions for week 2"
    And I click on "input[name='purpose'][value='questions']" "css_element"
    And I click on "Next" "button" in the ".modal-dialog" "css_element"
    And I click on "[data-wizard='go']" "css_element"
    And I wait until the page is ready
    Then I should see "Questions for week 2" in the "#section-2" "css_element"
