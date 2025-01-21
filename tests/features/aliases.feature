Feature: Gathering sites' aliases
  As a Pantheon user
  I need to be able to generate a list of aliases
  So that I may make use of Drush effectively.

  Background: I am authenticated
    Given I am authenticated

  @vcr aliases.yml
  Scenario: Generating aliases with printout
    When I run "[[executable]] aliases"
    Then I should get: "/.drush/pantheon.aliases.drushrc.php"

  @vcr aliases.yml
  Scenario: Generating aliases with printout
    When I run "[[executable]] aliases --location=[[cache_dir]]/aliases.php"
    Then I should get: "/aliases.php"

  @vcr aliases.yml
  Scenario: Generating aliases with printout
    When I run "[[executable]] aliases --print"
    Then I should get:
    """
     [notice] Fetching site information to build Drush aliases...
     [notice] 40 sites found.
     [notice] Displaying Drush 8 alias file contents.
    """
    And I should get:
    """
     $aliases['a-far-off-site-with-no-org.*'] = array(
       'uri' => '${env-name}-a-far-off-site-with-no-org.pantheonsite.io',
       'remote-host' => 'appserver.${env-name}.8a5311d2-162a-4a88-8561-ebf8706568cb.drush.in',
       'remote-user' => '${env-name}.8a5311d2-162a-4a88-8561-ebf8706568cb',
       'ssh-options' => '-p 2222',
       'path-aliases' => array(
         '%files' => 'files',
        ),
     );
    """
