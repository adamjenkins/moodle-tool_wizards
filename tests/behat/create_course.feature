@tool @tool_wizards
Feature: Create a course with the wizard
  In order to start teaching without learning every course setting
  As a course creator
  I need to create a course by answering a few simple questions

  Background:
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Science    | 0        | SCI      |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | creator1 | Casey     | Creator  | creator1@example.com |
    And the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | creator1 | coursecreator | Category     | SCI       |

  @javascript
  Scenario: A course creator answers the questions one at a time and gets a course they teach
    Given I log in as "creator1"
    And I am on the "tool_wizards > Course wizard" page
    Then I should see "What do you want the course called?"
    And I should see "Step 1 of"
    When I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "Please give the course a name."
    When I set the field "Course name" to "Introduction to Biology"
    And I set the field "Short name" to "BIO101"
    And I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "What layout do you want?"
    When I click on "Weekly sections" "text"
    And I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "How many sections do you want to start with?"
    When I set the field "Number of sections" to "6"
    And I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "Should students see the course now?"
    When I click on "Not yet, I will show it later" "text"
    And I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "When does the course start?"
    When I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "Ready to create your course"
    And I should see "Introduction to Biology"
    And I should see "Weekly sections"
    And I should see "Hidden for now"
    When I click on "Back" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "When does the course start?"
    When I click on "Next" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    And I click on "Create course" "button" in the "fieldset[data-step]:not([hidden])" "css_element"
    Then I should see "Introduction to Biology" in the "page-header" "region"
    And I should see "Your course is ready."
    And I should see "What would you like to add first?"
    And I am on the "BIO101" "Course" page
    And I navigate to course participants
    And I should see "Teacher" in the "Casey Creator" "table_row"

  Scenario: Without JavaScript the wizard is one form that still creates the course
    Given I log in as "creator1"
    And I am on the "tool_wizards > Course wizard" page
    When I set the following fields to these values:
      | Course name | Chemistry basics |
      | Short name  | CHEM1            |
    And I press "Create course"
    Then I should see "Chemistry basics"
    And I should see "Your course is ready."

  Scenario: The wizard is offered in the category menu
    Given I log in as "creator1"
    And I am on course index
    When I follow "Science"
    Then I should see "Create a course with the wizard"
