Feature: Looking up a site
  In order to know whether a site exists
  As a user
  I need to be able to detect if a site of a given name already exists

  Background: I am authenticated and I have a site named [[test_site_name]]
    Given I am authenticated
    And a site named "[[test_site_name]]"

  @vcr site_lookup
  Scenario: Site look-up
    When I run "terminus site:lookup [[test_site_name]]"
    Then I should get: "11111111-1111-1111-1111-111111111111"

  @vcr site_lookup_dne
  Scenario: Site look-up fails because site DNE
    When I run "terminus site:lookup invalid"
    Then I should get: "A site named invalid was not found."
