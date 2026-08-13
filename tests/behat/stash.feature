@tool @tool_pluginstash
Feature: Manage the Plugin Stash tool
  In order to keep my add-on plugins across rebuilds of a test site
  As an administrator
  I need to open the Plugin Stash page and control whether stashing is enabled

  Background:
    Given I log in as "admin"

  Scenario: The Plugin Stash page is available to administrators
    Given the following config values are set as admin:
      | enabled | 1 | tool_pluginstash |
    When I visit "/admin/tool/pluginstash/index.php"
    Then I should see "Plugin Stash"

  Scenario: A notice is shown when there are no add-on plugins to stash
    Given the following config values are set as admin:
      | enabled | 1 | tool_pluginstash |
    When I visit "/admin/tool/pluginstash/index.php"
    Then I should see "No additional plugins are installed"
    And I should not see "Stash selected plugins"

  Scenario: Disabling the tool locks the stash form
    Given the following config values are set as admin:
      | enabled | 0 | tool_pluginstash |
    When I visit "/admin/tool/pluginstash/index.php"
    Then I should see "Plugin stashing is currently disabled"
    And I should not see "Stash selected plugins"

  Scenario: Enabling the tool unlocks the stash page
    Given the following config values are set as admin:
      | enabled | 1 | tool_pluginstash |
    When I visit "/admin/tool/pluginstash/index.php"
    Then I should not see "Plugin stashing is currently disabled"
