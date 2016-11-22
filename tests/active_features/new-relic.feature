# Disabled until new relic model is ported and fixed.
#Feature: New Relic
#  In order to monitor my site's performance
#  As a user
#  I need to be able to view my New Relic data
#
#  Background: I am authenticated and have a site named [[test_site_name]]
#    Given I am authenticated
#    And a site named "[[test_site_name]]"
#
#  @vcr site_new-relic_status
#  Scenario: Accessing New Relic data
#    When I run "terminus new-relic:status [[test_site_name]]"
#    Then I should get: "--------------- --"
#    And I should get: "Name"
#    And I should get: "Status"
#    And I should get: "Subscribed On"
#    And I should get: "State"
#    And I should get: "--------------- --"
#
#  @vcr site_new-relic_enable
#  Scenario: Enabling New Relic data
#    When I run "terminus new-relic:enable [[test_site_name]]"
#    Then I should get: "New Relic enabled. Converging bindings."
#    And I should get: "Brought environments to desired configuration state"
#
#  @vcr site_new-relic_disable
#  Scenario: Disabling New Relic data
#    When I run "terminus new-relic:disable [[test_site_name]]"
#    Then I should get: "New Relic disabled. Converging bindings."
#    And I should get: "Brought environments to desired configuration state"
