@tool @tool_wizards
Feature: Add first content with a mini-wizard
  In order not to be left with an empty course
  As a teacher
  I need to add a first activity by answering a couple of questions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections |
      | Course 1 | C1        | topics | 3           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: The teacher adds a forum from the first-content suggestions
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should see "What would you like to add first?"
    When I click on "A forum" "button" in the "[data-region='tool_wizards-firstcontent']" "css_element"
    And I wait until the page is ready
    And I set the following fields to these values:
      | Forum name                         | Week 1 discussion |
      | What is this forum for? (optional) | Say hello!        |
    And I click on "Add" "button" in the ".modal-dialog" "css_element"
    And I wait until the page is ready
    Then I should see "Nice, Week 1 discussion was added."
    And I should see "What would you like to add next?"
    And I should see "Week 1 discussion" in the "region-main" "region"

  Scenario: Students never see the suggestions
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    Then I should not see "What would you like to add first?"

  Scenario: The suggestions stop once the course has content
    Given the following "activities" exist:
      | activity | course | name    |
      | page     | C1     | Page 1  |
      | page     | C1     | Page 2  |
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    Then I should not see "What would you like to add first?"
