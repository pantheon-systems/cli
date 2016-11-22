<?php

namespace Pantheon\Terminus;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use League\Container\Container;
use League\Container\ContainerInterface;
use Pantheon\Terminus\Collections\Backups;
use Pantheon\Terminus\Collections\Bindings;
use Pantheon\Terminus\Collections\Branches;
use Pantheon\Terminus\Collections\Commits;
use Pantheon\Terminus\Collections\Environments;
use Pantheon\Terminus\Collections\Hostnames;
use Pantheon\Terminus\Collections\Instruments;
use Pantheon\Terminus\Collections\MachineTokens;
use Pantheon\Terminus\Collections\OrganizationSiteMemberships;
use Pantheon\Terminus\Collections\OrganizationUserMemberships;
use Pantheon\Terminus\Collections\SavedTokens;
use Pantheon\Terminus\Collections\SiteOrganizationMemberships;
use Pantheon\Terminus\Collections\Sites;
use Pantheon\Terminus\Collections\SiteUserMemberships;
use Pantheon\Terminus\Collections\SshKeys;
use Pantheon\Terminus\Collections\Tags;
use Pantheon\Terminus\Collections\Upstreams;
use Pantheon\Terminus\Collections\UserOrganizationMemberships;
use Pantheon\Terminus\Collections\UserSiteMemberships;
use Pantheon\Terminus\Collections\Workflows;
use Pantheon\Terminus\Models\Backup;
use Pantheon\Terminus\Models\Binding;
use Pantheon\Terminus\Models\Branch;
use Pantheon\Terminus\Models\Commit;
use Pantheon\Terminus\Models\Environment;
use Pantheon\Terminus\Models\Hostname;
use Pantheon\Terminus\Models\Instrument;
use Pantheon\Terminus\Models\Lock;
use Pantheon\Terminus\Models\MachineToken;
use Pantheon\Terminus\Models\NewRelic;
use Pantheon\Terminus\Models\Organization;
use Pantheon\Terminus\Models\OrganizationSiteMembership;
use Pantheon\Terminus\Models\OrganizationUserMembership;
use Pantheon\Terminus\Models\Redis;
use Pantheon\Terminus\Models\SavedToken;
use Pantheon\Terminus\Models\Site;
use Pantheon\Terminus\Models\SiteOrganizationMembership;
use Pantheon\Terminus\Models\SiteUserMembership;
use Pantheon\Terminus\Models\Solr;
use Pantheon\Terminus\Models\SshKey;
use Pantheon\Terminus\Models\Tag;
use Pantheon\Terminus\Models\Upstream;
use Pantheon\Terminus\Models\User;
use Pantheon\Terminus\Models\UserOrganizationMembership;
use Pantheon\Terminus\Models\UserSiteMembership;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\Models\WorkflowOperation;
use Pantheon\Terminus\Request\Request;
use Pantheon\Terminus\Request\RequestAwareInterface;
use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\Session\SessionAwareInterface;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Robo\Runner as RoboRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Terminus\Caches\FileCache;
use VCR\VCR;

class Runner
{
    /**
     * @var \Robo\Runner
     */
    private $runner;
    /**
     * @var string[]
     */
    private $commands = [];
    /**
     * @var Config
     */
    private $config;

    /**
     * Object constructor
     *
     * @param Container $container Container The dependency injection container
     */
    public function __construct(Container $container = null)
    {
        $this->config = $container->get('config');

        $this->configureContainer($container);

        $this->runner = new RoboRunner();
        $this->runner->setContainer($container);

        date_default_timezone_set($this->config->get('time_zone'));
    }

    /**
     * Runs the instantiated Terminus application
     *
     * @param InputInterface  $input  An input object to run the application with
     * @param OutputInterface $output An output object to run the application with
     * @return integer $status_code The exiting status code of the application
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        if (!empty($cassette = $this->config->get('vcr_cassette')) && !empty($mode = $this->config->get('vcr_mode'))) {
            $this->startVCR(array_merge(compact('cassette'), compact('mode')));
        }
        $status_code = $this->runner->run($input, $output, null, $this->commands);
        if (!empty($cassette) && !empty($mode)) {
            $this->stopVCR();
        }
        return $status_code;
    }

    /**
     * Discovers command classes using CommandFileDiscovery
     *
     * @param string[] $options Elements as follow
     *        string path      The full path to the directory to search for commands
     *        string namespace The full namespace associated with given the command directory
     * @return TerminusCommand[] An array of TerminusCommand instances
     */
    private function getCommands(array $options = ['path' => null, 'namespace' => null,])
    {
        $discovery = new CommandFileDiscovery();
        $discovery->setSearchPattern('*Command.php')->setSearchLocations([]);
        return $discovery->discover($options['path'], $options['namespace']);
    }

    /**
     * Register the necessary classes for Terminus
     *
     * @param \League\Container\ContainerInterface $container
     */
    private function configureContainer(ContainerInterface $container)
    {
        // Add the services.
        $container->share('request', Request::class);
        $container->inflector(RequestAwareInterface::class)
            ->invokeMethod('setRequest', ['request']);

        $container->share('fileCache', FileCache::class);

        $container->share('session', Session::class)
            ->withArgument('fileCache');
        $container->inflector(SessionAwareInterface::class)
            ->invokeMethod('setSession', ['session']);

        // Add the models and collections
        $container->add(User::class);
        $container->add(SavedTokens::class);
        $container->add(SavedToken::class);
        $container->add(Instruments::class);
        $container->add(Instrument::class);
        $container->add(SshKeys::class);
        $container->add(SshKey::class);
        $container->add(Workflows::class);
        $container->add(Workflow::class);
        $container->add(WorkflowOperation::class);
        $container->add(MachineTokens::class);
        $container->add(MachineToken::class);
        $container->add(Upstream::class);
        $container->add(Upstreams::class);
        $container->add(UserSiteMemberships::class);
        $container->add(UserSiteMembership::class);
        $container->add(UserOrganizationMemberships::class);
        $container->add(UserOrganizationMembership::class);
        $container->add(OrganizationSiteMemberships::class);
        $container->add(OrganizationSiteMembership::class);
        $container->add(OrganizationUserMemberships::class);
        $container->add(OrganizationUserMembership::class);
        $container->add(Organization::class);
        $container->add(Branches::class);
        $container->add(Branch::class);
        $container->add(SiteUserMemberships::class);
        $container->add(SiteUserMembership::class);
        $container->add(SiteOrganizationMemberships::class);
        $container->add(SiteOrganizationMembership::class);
        $container->add(Site::class);
        $container->add(Redis::class);
        $container->add(Solr::class);
        $container->add(Environments::class);
        $container->add(Environment::class);
        $container->add(Backups::class);
        $container->add(Backup::class);
        $container->add(Lock::class);
        $container->add(Bindings::class);
        $container->add(Binding::class);
        $container->add(Hostnames::class);
        $container->add(Hostname::class);
        $container->add(Commits::class);
        $container->add(Commit::class);
        $container->add(NewRelic::class);
        $container->add(Tags::class);
        $container->add(Tag::class);

        $container->share('sites', Sites::class);
        $container->inflector(SiteAwareInterface::class)
            ->invokeMethod('setSites', ['sites']);

        // TODO: Add more models and collections

        // Add the commands.
        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);

        $commands_directory = __DIR__ . '/Commands';
        $top_namespace = 'Pantheon\Terminus\Commands';
        $this->commands = $this->getCommands(['path' => $commands_directory, 'namespace' => $top_namespace,]);
        $this->commands[] = 'Pantheon\\Terminus\\Authorizer';
    }

    /**
     * Starts and configures PHP-VCR
     *
     * @param string[] $options Elements as follow:
     *        string cassette The name of the fixture in tests/fixtures to record or run in this feature test run
     *        string mode     Mode in which to run PHP-VCR (options are none, once, strict, and new_episodes)
     * @return void
     */
    private function startVCR(array $options = ['cassette' => 'tmp', 'mode' => 'none',])
    {
        VCR::configure()->enableRequestMatchers(['method', 'url', 'body',]);
        VCR::configure()->setMode($options['mode']);
        VCR::turnOn();
        VCR::insertCassette($options['cassette']);
    }

    /**
     * Stops PHP-VCR's recording and playback
     *
     * @return void
     */
    private function stopVCR()
    {
        VCR::eject();
        VCR::turnOff();
    }
}
