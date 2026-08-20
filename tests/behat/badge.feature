@local @local_coursegradebadge @javascript
Feature: Course grade badge on the course overview cards
  In order to know how I am doing without opening every course
  As a student
  I need to see my total course grade on the dashboard cards

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  Scenario: A student with a course total sees the badge on the card
    Given the course total grade for "student1" in course "C1" is "85"
    When I am on the "My courses" page logged in as "student1"
    Then I should see "Course total grade" in the ".lcgb-badge" "css_element"
    And I should see "85" in the ".lcgb-badge" "css_element"

  Scenario: A student without a grade gets no badge
    When I am on the "My courses" page logged in as "student1"
    And I wait until the page is ready
    Then "[data-region='course-content']" "css_element" should exist
    And ".lcgb-badge" "css_element" should not exist

  Scenario: A teacher gets no badge
    Given the course total grade for "teacher1" in course "C1" is "70"
    When I am on the "My courses" page logged in as "teacher1"
    And I wait until the page is ready
    Then "[data-region='course-content']" "css_element" should exist
    And ".lcgb-badge" "css_element" should not exist
