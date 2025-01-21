<?php

namespace Pantheon\Terminus\UnitTests\Commands;

use League\Container\Container;
use Pantheon\Terminus\Commands\AliasesCommand;
use Robo\Config;
use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\UnitTests\Helpers\AliasFixtures;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class AliasesCommandTest
 * Testing class for Pantheon\Terminus\Commands\AliasesCommand
 * @package Pantheon\Terminus\UnitTests\Commands
 */
class AliasesCommandTest extends CommandTestCase
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Container
     */
    protected $container;
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var string
     */
    protected $home_dir;
    /**
     * @var BufferedOutput
     */
    protected $output;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->fixtures = new AliasFixtures();
        $this->output = new BufferedOutput();

        $this->home_dir = realpath($this->fixtures->mktmpdir());

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->getConfig()->method('get')
            ->with($this->equalTo('user_home'))
            ->willReturn($this->home_dir);
        $this->sites->method('fetch')
            ->willReturn(null);
        $this->sites->method('ids')
            ->willReturn([$this->site->id]);
        $this->sites->method('serialize')
            ->willReturn([$this->site->id => ['id' => $this->site->id, 'name' => 'site1']]);
        $this->site->method('get')
            ->willReturn('site1');

        $this->command = new AliasesCommand($this->getConfig());
        $this->command->setLogger($this->logger);
        $this->command->setSession($this->session);
        $this->command->setContainer($this->container);
        $this->command->setConfig($this->config);
        $this->command->setSites($this->sites);
        $this->command->setOutput($this->output);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        $this->fixtures->cleanup();
    }

    /**
     * Tests the aliases command when writing to a the default file
     */
    public function testAliases()
    {
        $this->logger->expects($this->at(0))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Fetching site information to build Drush aliases...')
            );
        $this->logger->expects($this->at(1))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('{count} sites found.'),
                $this->equalTo(['count' => 1])
            );
        $this->logger->expects($this->at(2))
            ->method('log')
            ->with(
                $this->equalTo('debug'),
                $this->equalTo("Emitting aliases via {emitter}"),
                $this->equalTo(['emitter' => 'Pantheon\Terminus\Helpers\AliasEmitters\AliasesDrushRcEmitter'])
            );
        $this->logger->expects($this->at(3))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->stringContains(".drush/pantheon.aliases.drushrc.php")
            );
        $this->logger->expects($this->at(4))
            ->method('log')
            ->with(
                $this->equalTo('debug'),
                $this->equalTo("Emitting aliases via {emitter}"),
                $this->equalTo(['emitter' => 'Pantheon\Terminus\Helpers\AliasEmitters\DrushSitesYmlEmitter'])
            );
        $this->logger->expects($this->at(5))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->stringContains(".drush/sites/pantheon")
            );

        $out = $this->command->aliases();
        $this->assertNull($out);

        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $this->MarkTestSkipped("Temp file handling on Windows is not working correctly in this test.");
        }

        $expected_drush_8_alias_path = $this->home_dir . '/.drush/pantheon.aliases.drushrc.php';
        $this->assertFileExists($expected_drush_8_alias_path);
        $drush_8_aliases = file_get_contents($expected_drush_8_alias_path);

        $this->assertEquals($this->expectedDrush8AliasOutput(), trim($drush_8_aliases));

        $expected_drush_9_alias_path = $this->home_dir . '/.drush/sites/pantheon/site1.site.yml';
        $this->assertFileExists($expected_drush_9_alias_path);
        $drush_9_aliases = file_get_contents($expected_drush_9_alias_path);
        $expected = <<<__EOT__
'*':
  host: appserver.\${env-name}.abc.drush.in
  paths:
    files: files
  uri: \${env-name}-site1.pantheonsite.io
  user: \${env-name}.abc
  ssh:
    options: '-p 2222'
    tty: false
__EOT__;
        $this->assertEquals($expected, trim($drush_9_aliases));
    }

    /**
     * Tests the aliases command when it is outputting to the screen
     */
    public function testAliasesPrint()
    {
        $this->logger->expects($this->at(0))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Fetching site information to build Drush aliases...')
            );
        $this->logger->expects($this->at(1))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('{count} sites found.'),
                $this->equalTo(['count' => 1])
            );
        $this->logger->expects($this->at(2))
            ->method('log')
            ->with(
                $this->equalTo('debug'),
                $this->equalTo("Emitting aliases via {emitter}"),
                $this->equalTo(['emitter' => 'Pantheon\Terminus\Helpers\AliasEmitters\PrintingEmitter'])
            );
        $this->logger->expects($this->at(3))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Displaying Drush 8 alias file contents.')
            );

        $this->container->expects($this->never())
            ->method('get');

        $out = $this->command->aliases([
            'print' => true,
            'location' => null,
            'all' => false,
            'only' => '',
            'type' => 'all',
            'base' => '~/.drush',
            'db-url' => true,
            'target' => 'pantheon',
        ]);
        $this->assertNull($out);

        $this->assertEquals($this->expectedDrush8AliasOutput(), trim($this->output->fetch()));
    }

    /**
     * Return the expected output for Drush 8 alias files.
     */
    protected function expectedDrush8AliasOutput()
    {
        $expected = <<<__EOT__
<?php
  /**
   * Pantheon drush alias file, to be placed in your ~/.drush directory or the aliases
   * directory of your local Drush home. Once it's in place, clear drush cache:
   *
   * drush cc drush
   *
   * To see all your available aliases:
   *
   * drush sa
   *
   * See http://helpdesk.getpantheon.com/customer/portal/articles/411388 for details.
   */
  \$aliases['site1.*'] = array(
    'uri' => '\${env-name}-site1.pantheonsite.io',
    'remote-host' => 'appserver.\${env-name}.abc.drush.in',
    'remote-user' => '\${env-name}.abc',
    'ssh-options' => '-p 2222',
    'path-aliases' => array(
      '%files' => 'files',
     ),
  );
__EOT__;
        return $expected;
    }
}
