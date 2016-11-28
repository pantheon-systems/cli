Feature: Organizational users
  In order to coordinate users within organizations
  As an organizational user
  I need to be able to list organizational user memberships.

  Background: I am authenticated
    Given I am authenticated

  @vcr organizations_team_list
  Scenario: List an organization's teammates
    When I run "terminus org:team:list '[[organization_name]]'"
    Then I should get: "-------------------------------------- ------------ ----------- ----------------------- -----------"
    And I should get: "ID                                     First Name   Last Name   Email                   Role"
    And I should get: "-------------------------------------- ------------ ----------- ----------------------- -----------"
    And I should get: "a7926bb1-9490-46eb-b580-2e80cdf9fd11   Dev          User        [[other_user]]   developer"
    And I should get: "11111111-1111-1111-1111-111111111111   Dev          User        [[username]]     admin"
    And I should get: "-------------------------------------- ------------ ----------- ----------------------- -----------"

  @vcr org_team_list_empty.yml
  Scenario: List an organization's teammates
    When I run "terminus org:team:list '[[organization_name]]'"
    Then I should get: "[[organization_name]] has no team members."
    And I should get: "---- ------------ ----------- ------- ------"
    And I should get: "ID   First Name   Last Name   Email   Role"
    And I should get: "---- ------------ ----------- ------- ------"

  @vcr organizations_team_add-member
  Scenario: Add a new member to a team
    When I run "terminus org:team:add '[[organization_name]]' [[other_user]] team_member"
    Then I should get: "[[other_user]] has been added to the [[organization_name]] organization as a(n) team_member."

  @vcr organizations_team_remove-member
  Scenario: Removing a new member from a team
    When I run "terminus org:team:remove '[[organization_name]]' [[other_user]]"
    Then I should get: "Dev User has been removed from the [[organization_name]] organization."

  @vcr organizations_team_change-role
  Scenario: Changing a team member's role
    When I run "terminus org:team:role '[[organization_name]]' [[other_user]] developer"
    Then I should get: "Dev User's role has been changed to developer in the [[organization_name]] organization."
