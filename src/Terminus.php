<?php

declare(strict_types=1);

namespace Pantheon\Terminus;

use Composer\Autoload\ClassLoader;
use League\Container\Container;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Pantheon\Terminus\Collections\SavedTokens;
use Pantheon\Terminus\Collections\Sites;
use Pantheon\Terminus\Config\ConfigAwareTrait;
use Pantheon\Terminus\DataStore\FileStore;
use Pantheon\Terminus\Helpers\LocalMachineHelper;
use Pantheon\Terminus\Helpers\Traits\CommandExecutorTrait;
use Pantheon\Terminus\Helpers\Utility\TraceId;
use Pantheon\Terminus\Helpers\Utility\Timing;
use Pantheon\Terminus\Plugins\PluginDiscovery;
use Pantheon\Terminus\Plugins\PluginInfo;
use Pantheon\Terminus\ProgressBars\ProcessProgressBar;
use Pantheon\Terminus\ProgressBars\WorkflowProgressBar;
use Pantheon\Terminus\Request\Request;
use Pantheon\Terminus\Request\RequestAwareInterface;
use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\Session\SessionAwareInterface;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Common\IO;
use Robo\Config\Config;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Robo;
use Robo\Runner as RoboRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use VCR\VCR;
use Pantheon\Terminus\Config\DefaultsConfig;
use Pantheon\Terminus\Config\DotEnvConfig;
use Pantheon\Terminus\Config\EnvConfig;
use Pantheon\Terminus\Config\YamlConfig;
use Symfony\Component\Filesystem\Filesystem;
use SelfUpdate\SelfUpdateCommand;
use Pantheon\Terminus\Hooks\CommandTracker;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Terminus
 *
 * @package Pantheon\Terminus
 */
class Terminus implements
    ConfigAwareInterface,
    ContainerAwareInterface,
    LoggerAwareInterface,
    IOAwareInterface
{
    use ConfigAwareTrait;
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use CommandExecutorTrait;
    use IO;

    /**
     * @var \Robo\Runner
     */
    private $runner;
    /**
     * @var string[]
     */
    private $commands = [];

    private Application $application;

    /**
     * Object constructor
     *
     * @param \Robo\Config\Config $config
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function __construct(Config $config, InputInterface $input, OutputInterface $output)
    {
        $this->setConfig($config);
        $this->setInput($input);
        $this->setOutput($output);
        $application = new Application('Terminus', $config->get('version'));
        $options = $application->getDefinition()->getOptions();
        if (isset($options['verbose'])) {
            $originalVerboseOption = $options['verbose'];
            $description = <<<EOD
Increase the verbosity of messages: 1 for normal output (-v), 2 for more verbose output (-vv), and 3 for debug (-vvv)
EOD;
            $options['verbose'] = new InputOption(
                $originalVerboseOption->getName(),
                $originalVerboseOption->getShortcut(),
                InputOption::VALUE_NONE,
                $description
            );
            $application->getDefinition()->setOptions($options);
        }
        $application->getDefinition()
            ->addOption(
                new InputOption(
                    '--define',
                    '-D',
                    InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    'Define a configuration item value.',
                    []
                )
            );

        $container = new Container();
        Robo::configureContainer(
            $container,
            $application,
            $config,
            $input,
            $output
        );
        $this->setContainer($container);
        $this->addDefaultArgumentsAndOptions($application);
        $this->configureContainer();
        Robo::finalizeContainer($this->getContainer());
        $this->setLogger($container->get('logger'));
        $this->addBuiltInCommandsAndHooks();
        $this->addPluginsCommandsAndHooks();

        $container->get('eventDispatcher')->addSubscriber($container->get('Pantheon\Terminus\Hooks\CommandTracker'));

        // We can't use Robo\Application addSelfUpdateCommand because if plugin manager is running it won't be a phar from there.
        if (!empty(\Phar::running())) {
            $selfUpdateCommand = new SelfUpdateCommand(
                $application->getName(),
                $application->getVersion(),
                'pantheon-systems/terminus'
            );
            $selfUpdateCommand->ignorePharRunningCheck();
            $application->add($selfUpdateCommand);
        }

        $this->setApplication($application);
        $this->runner = new RoboRunner();
        $this->runner->setContainer($container);

        date_default_timezone_set($config->get('time_zone'));
        setlocale(LC_MONETARY, $config->get('monetary_locale'));
    }

    /**
     * Add any global arguments or options that apply to all commands.
     *
     * @param \Symfony\Component\Console\Application $app
     */
    private function addDefaultArgumentsAndOptions(Application $app)
    {
        $app->getDefinition()->addOption(
            new InputOption('--yes', '-y', InputOption::VALUE_NONE, 'Answer all confirmations with "yes"')
        );
    }

    /**
     * Register the necessary classes for Terminus
     */
    private function configureContainer()
    {
        $container = $this->getContainer();

        // Generate the trace ID
        TraceId::generateTraceId();

        // Generate the time at which Terminus was started
        Timing::generateStartTime();

        // Add the services
        // Request
        $container->addShared('request', Request::class);
        $container->inflector(RequestAwareInterface::class)
            ->invokeMethod('setRequest', ['request']);

        // Session
        $session_store = new FileStore(
            $this->getConfig()->get('cache_dir')
        );
        $session = new Session($session_store);
        $container->addShared('session', $session);
        $container->inflector(SessionAwareInterface::class)
            ->invokeMethod('setSession', ['session']);

        // Saved tokens
        $token_store = new FileStore($this->getConfig()->get('tokens_dir'));
        $container->inflector(SavedTokens::class)
            ->invokeMethod('setDataStore', [$token_store]);

        $this->configureModulesAndCollections($container);

        // Helpers
        $container->add(LocalMachineHelper::class);

        // Progress Bars
        $container->add(ProcessProgressBar::class);
        $container->add(WorkflowProgressBar::class);

        // Plugin handlers
        $container->add(PluginDiscovery::class);
        $container->add(PluginInfo::class);

        $container->addShared('sites', Sites::class);
        $container->inflector(SiteAwareInterface::class)
            ->invokeMethod('setSites', ['sites']);

        // Command Tracker
        $container->add(CommandTracker::class);

        // Install our command cache into the command factory
        $commandCacheDir = $this->getConfig()->get('command_cache_dir');
        if (!is_dir($commandCacheDir)) {
            mkdir($commandCacheDir);
        }
        $commandCacheDataStore = new FileStore($commandCacheDir);

        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);
        $factory->setDataStore($commandCacheDataStore);
    }

    private function configureModulesAndCollections($container)
    {
        // List of all Models and Collections. Update via 'composer update-class-lists'

        // Models
        $container->add(\Pantheon\Terminus\Models\Backup::class);
        $container->add(\Pantheon\Terminus\Models\Binding::class);
        $container->add(\Pantheon\Terminus\Models\Branch::class);
        $container->add(\Pantheon\Terminus\Models\Commit::class);
        $container->add(\Pantheon\Terminus\Models\DNSRecord::class);
        $container->add(\Pantheon\Terminus\Models\Domain::class);
        $container->add(\Pantheon\Terminus\Models\Environment::class);
        $container->add(\Pantheon\Terminus\Models\Lock::class);
        $container->add(\Pantheon\Terminus\Models\MachineToken::class);
        $container->add(\Pantheon\Terminus\Models\Metric::class);
        $container->add(\Pantheon\Terminus\Models\NewRelic::class);
        $container->add(\Pantheon\Terminus\Models\Organization::class);
        $container->add(\Pantheon\Terminus\Models\OrganizationSiteMembership::class);
        $container->add(\Pantheon\Terminus\Models\OrganizationUpstream::class);
        $container->add(\Pantheon\Terminus\Models\OrganizationUserMembership::class);
        $container->add(\Pantheon\Terminus\Models\PaymentMethod::class);
        $container->add(\Pantheon\Terminus\Models\Plan::class);
        $container->add(\Pantheon\Terminus\Models\PrimaryDomain::class);
        $container->add(\Pantheon\Terminus\Models\Profile::class);
        $container->add(\Pantheon\Terminus\Models\Redis::class);
        $container->add(\Pantheon\Terminus\Models\SSHKey::class);
        $container->add(\Pantheon\Terminus\Models\SavedToken::class);
        $container->add(\Pantheon\Terminus\Models\Site::class);
        $container->add(\Pantheon\Terminus\Models\SiteAuthorization::class);
        $container->add(\Pantheon\Terminus\Models\SiteOrganizationMembership::class);
        $container->add(\Pantheon\Terminus\Models\SiteUpstream::class);
        $container->add(\Pantheon\Terminus\Models\SiteUserMembership::class);
        $container->add(\Pantheon\Terminus\Models\Solr::class);
        $container->add(\Pantheon\Terminus\Models\Tag::class);
        $container->add(\Pantheon\Terminus\Models\Upstream::class);
        $container->add(\Pantheon\Terminus\Models\UpstreamStatus::class);
        $container->add(\Pantheon\Terminus\Models\User::class);
        $container->add(\Pantheon\Terminus\Models\UserOrganizationMembership::class);
        $container->add(\Pantheon\Terminus\Models\UserSiteMembership::class);
        $container->add(\Pantheon\Terminus\Models\Workflow::class);
        $container->add(\Pantheon\Terminus\Models\WorkflowLog::class);
        $container->add(\Pantheon\Terminus\Models\WorkflowLogActor::class);
        $container->add(\Pantheon\Terminus\Models\WorkflowLogInfo::class);
        $container->add(\Pantheon\Terminus\Models\WorkflowOperation::class);

        // Collections
        $container->add(\Pantheon\Terminus\Collections\Backups::class);
        $container->add(\Pantheon\Terminus\Collections\Bindings::class);
        $container->add(\Pantheon\Terminus\Collections\Branches::class);
        $container->add(\Pantheon\Terminus\Collections\Commits::class);
        $container->add(\Pantheon\Terminus\Collections\DNSRecords::class);
        $container->add(\Pantheon\Terminus\Collections\Domains::class);
        $container->add(\Pantheon\Terminus\Collections\EnvironmentMetrics::class);
        $container->add(\Pantheon\Terminus\Collections\Environments::class);
        $container->add(\Pantheon\Terminus\Collections\MachineTokens::class);
        $container->add(\Pantheon\Terminus\Collections\OrganizationSiteMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\OrganizationUpstreams::class);
        $container->add(\Pantheon\Terminus\Collections\OrganizationUserMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\Organizations::class);
        $container->add(\Pantheon\Terminus\Collections\PaymentMethods::class);
        $container->add(\Pantheon\Terminus\Collections\Plans::class);
        $container->add(\Pantheon\Terminus\Collections\SSHKeys::class);
        $container->add(\Pantheon\Terminus\Collections\SavedTokens::class);
        $container->add(\Pantheon\Terminus\Collections\SiteAuthorizations::class);
        $container->add(\Pantheon\Terminus\Collections\SiteMetrics::class);
        $container->add(\Pantheon\Terminus\Collections\SiteOrganizationMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\SiteUserMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\Sites::class);
        $container->add(\Pantheon\Terminus\Collections\Tags::class);
        $container->add(\Pantheon\Terminus\Collections\Upstreams::class);
        $container->add(\Pantheon\Terminus\Collections\UserOrganizationMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\UserSiteMemberships::class);
        $container->add(\Pantheon\Terminus\Collections\WorkflowLogsCollection::class);
        $container->add(\Pantheon\Terminus\Collections\WorkflowOperations::class);
        $container->add(\Pantheon\Terminus\Collections\Workflows::class);
    }

    /**
     * Add the commands and hooks which are shipped with core Terminus
     */
    private function addBuiltInCommandsAndHooks()
    {
        // List of all hooks and commands. Update via 'composer update-class-lists'
        $this->commands = [
            'Pantheon\\Terminus\\Hooks\\Authorizer',
            'Pantheon\\Terminus\\Hooks\\CommandTracker',
            'Pantheon\\Terminus\\Hooks\\RoleValidator',
            'Pantheon\\Terminus\\Hooks\\SiteEnvLookup',
            'Pantheon\\Terminus\\Commands\\AliasesCommand',
            'Pantheon\\Terminus\\Commands\\ArtCommand',
            'Pantheon\\Terminus\\Commands\\Auth\\LoginCommand',
            'Pantheon\\Terminus\\Commands\\Auth\\LogoutCommand',
            'Pantheon\\Terminus\\Commands\\Auth\\WhoamiCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\Automatic\\DisableCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\Automatic\\EnableCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\Automatic\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\BackupCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\CreateCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\GetCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\RestoreCommand',
            'Pantheon\\Terminus\\Commands\\Backup\\SingleBackupCommand',
            'Pantheon\\Terminus\\Commands\\Branch\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Connection\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Connection\\SetCommand',
            'Pantheon\\Terminus\\Commands\\Dashboard\\ViewCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\DNSCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\LookupCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\Primary\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\Primary\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Domain\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Env\\ClearCacheCommand',
            'Pantheon\\Terminus\\Commands\\Env\\CloneContentCommand',
            'Pantheon\\Terminus\\Commands\\Env\\CodeLogCommand',
            'Pantheon\\Terminus\\Commands\\Env\\CodeRebuildCommand',
            'Pantheon\\Terminus\\Commands\\Env\\CommitCommand',
            'Pantheon\\Terminus\\Commands\\Env\\DeployCommand',
            'Pantheon\\Terminus\\Commands\\Env\\DiffStatCommand',
            'Pantheon\\Terminus\\Commands\\Env\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Env\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Env\\MetricsCommand',
            'Pantheon\\Terminus\\Commands\\Env\\RotateRandomSeedCommand',
            'Pantheon\\Terminus\\Commands\\Env\\ViewCommand',
            'Pantheon\\Terminus\\Commands\\Env\\WakeCommand',
            'Pantheon\\Terminus\\Commands\\Env\\WipeCommand',
            'Pantheon\\Terminus\\Commands\\HTTPS\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\HTTPS\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\HTTPS\\SetCommand',
            'Pantheon\\Terminus\\Commands\\Import\\CompleteCommand',
            'Pantheon\\Terminus\\Commands\\Import\\DatabaseCommand',
            'Pantheon\\Terminus\\Commands\\Import\\FilesCommand',
            'Pantheon\\Terminus\\Commands\\Import\\SiteCommand',
            'Pantheon\\Terminus\\Commands\\Local\\CloneCommand',
            'Pantheon\\Terminus\\Commands\\Local\\CommitAndPushCommand',
            'Pantheon\\Terminus\\Commands\\Local\\DockerizeCommand',
            'Pantheon\\Terminus\\Commands\\Local\\GetLiveDBCommand',
            'Pantheon\\Terminus\\Commands\\Local\\GetLiveFilesCommand',
            'Pantheon\\Terminus\\Commands\\Lock\\DisableCommand',
            'Pantheon\\Terminus\\Commands\\Lock\\EnableCommand',
            'Pantheon\\Terminus\\Commands\\Lock\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\MachineToken\\DeleteAllCommand',
            'Pantheon\\Terminus\\Commands\\MachineToken\\DeleteCommand',
            'Pantheon\\Terminus\\Commands\\MachineToken\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Multidev\\CreateCommand',
            'Pantheon\\Terminus\\Commands\\Multidev\\DeleteCommand',
            'Pantheon\\Terminus\\Commands\\Multidev\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Multidev\\MergeFromDevCommand',
            'Pantheon\\Terminus\\Commands\\Multidev\\MergeToDevCommand',
            'Pantheon\\Terminus\\Commands\\NewRelic\\DisableCommand',
            'Pantheon\\Terminus\\Commands\\NewRelic\\EnableCommand',
            'Pantheon\\Terminus\\Commands\\NewRelic\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Org\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Org\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Org\\People\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Org\\People\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Org\\People\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Org\\People\\RoleCommand',
            'Pantheon\\Terminus\\Commands\\Org\\Site\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Org\\Site\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Org\\Upstream\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Owner\\SetCommand',
            'Pantheon\\Terminus\\Commands\\PaymentMethod\\AddCommand',
            'Pantheon\\Terminus\\Commands\\PaymentMethod\\ListCommand',
            'Pantheon\\Terminus\\Commands\\PaymentMethod\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Plan\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Plan\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Plan\\SetCommand',
            'Pantheon\\Terminus\\Commands\\Redis\\DisableCommand',
            'Pantheon\\Terminus\\Commands\\Redis\\EnableCommand',
            'Pantheon\\Terminus\\Commands\\Remote\\DrushCommand',
            'Pantheon\\Terminus\\Commands\\Remote\\SSHBaseCommand',
            'Pantheon\\Terminus\\Commands\\Remote\\WPCommand',
            'Pantheon\\Terminus\\Commands\\SSHKey\\AddCommand',
            'Pantheon\\Terminus\\Commands\\SSHKey\\ListCommand',
            'Pantheon\\Terminus\\Commands\\SSHKey\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Self\\ClearCacheCommand',
            'Pantheon\\Terminus\\Commands\\Self\\ConfigDumpCommand',
            'Pantheon\\Terminus\\Commands\\Self\\ConsoleCommand',
            'Pantheon\\Terminus\\Commands\\Self\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\CreateCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\InstallCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\PluginBaseCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\ReloadCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\SearchCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\UninstallCommand',
            'Pantheon\\Terminus\\Commands\\Self\\Plugin\\UpdateCommand',
            'Pantheon\\Terminus\\Commands\\ServiceLevel\\SetCommand',
            'Pantheon\\Terminus\\Commands\\Site\\CreateCommand',
            'Pantheon\\Terminus\\Commands\\Site\\DeleteCommand',
            'Pantheon\\Terminus\\Commands\\Site\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Site\\LabelCommand',
            'Pantheon\\Terminus\\Commands\\Site\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Site\\LookupCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Org\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Org\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Org\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Site\\SiteCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Team\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Team\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Team\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Team\\RoleCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Upstream\\ClearCacheCommand',
            'Pantheon\\Terminus\\Commands\\Site\\Upstream\\SetCommand',
            'Pantheon\\Terminus\\Commands\\Solr\\DisableCommand',
            'Pantheon\\Terminus\\Commands\\Solr\\EnableCommand',
            'Pantheon\\Terminus\\Commands\\Tag\\AddCommand',
            'Pantheon\\Terminus\\Commands\\Tag\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Tag\\RemoveCommand',
            'Pantheon\\Terminus\\Commands\\Tag\\TagCommand',
            'Pantheon\\Terminus\\Commands\\TerminusCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\InfoCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\Updates\\ApplyCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\Updates\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\Updates\\StatusCommand',
            'Pantheon\\Terminus\\Commands\\Upstream\\Updates\\UpdatesCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\Info\\InfoBaseCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\Info\\LogsCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\Info\\OperationsCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\Info\\StatusCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\ListCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\WaitCommand',
            'Pantheon\\Terminus\\Commands\\Workflow\\WatchCommand'
        ];

        // Add in any static additions not handled by 'composer update-class-lists'
        $this->commands[] = 'Consolidation\\Filter\\Hooks\\FilterHooks';
    }

    /**
     * Discovers command classes using CommandFileDiscovery
     */
    private function addPluginsCommandsAndHooks()
    {
        // Rudimentary plugin loading.
        $discovery = $this->getContainer()->get(PluginDiscovery::class);
        $plugins = $discovery->discover();
        $version = $this->config->get('version');
        $classLoader = new ClassLoader();
        $classLoader->register();
        foreach ($plugins as $plugin) {
            if ($plugin->isVersionCompatible()) {
                $this->commands += $plugin->getCommandsAndHooks();
            } else {
                $this->logger->warning(
                    "Could not load plugin {plugin} because it is not compatible with this version of Terminus.",
                    ['plugin' => $plugin->getName()]
                );
            }
        }
    }

    /**
     * Runs the instantiated Terminus application
     *
     * @param InputInterface $input An input object to run the application with
     * @param OutputInterface $output An output object to run the application with
     *
     * @return integer $status_code The exiting status code of the application
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null)
    {
        if ($input === null) {
            $input = $this->input();
        }
        if ($output === null) {
            $output = $this->output();
        }
        $config = $this->getConfig();
        if (!empty($cassette = $config->get('vcr_cassette')) && !empty($mode = $config->get('vcr_mode'))) {
            $this->startVCR(array_merge(compact('cassette'), compact('mode')));
        }
        $status_code = $this->runner->run($input, $output, $this->getApplication(), $this->commands);
        if (!empty($cassette) && !empty($mode)) {
            $this->stopVCR();
        }
        return $status_code;
    }

    /**
     * Starts and configures PHP-VCR
     *
     * @param string[] $options Elements as follow:
     *        string cassette The name of the fixture in tests/fixtures to record or run in this feature test run
     *        string mode     Mode in which to run PHP-VCR (options are none, once, strict, and new_episodes)
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
     */
    private function stopVCR()
    {
        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->application;
    }

    /**
     * @param Application $application
     */
    public function setApplication(Application $application): void
    {
        $this->application = $application;
    }

    /**
     * @return string[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @param string[] $commands
     */
    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * Determines whether Terminus is supposed to have plugins or not without loading them.
     */
    public function hasPlugins(): bool
    {
        $pluginsDir = $this->getConfig()->get('plugins_dir');
        if (!(new Filesystem())->exists($pluginsDir . DIRECTORY_SEPARATOR . 'composer.json')) {
            return false;
        }

        $composerJsonContents = json_decode(
            file_get_contents($pluginsDir . DIRECTORY_SEPARATOR . 'composer.json'),
            true
        );
        $dependencies = $composerJsonContents['require'] ?? [];

        return count($dependencies) > 0;
    }

    public static function factory($dependencies_version = null): Terminus
    {
        $input = new ArgvInput($_SERVER['argv']);
        $output = new ConsoleOutput();
        $config = new DefaultsConfig();
        $config->extend(new YamlConfig($config->get('root') . '/config/constants.yml'));
        $config->extend(new YamlConfig($config->get('user_home') . '/.terminus/config.yml'));
        $config->extend(new DotEnvConfig(getcwd()));
        $config->extend(new EnvConfig());
        $dependencies_folder_absent = false;
        if ($dependencies_version) {
            $dependenciesBaseDir = $config->get('dependencies_base_dir');
            $terminusDependenciesDir = $dependenciesBaseDir . '-' . $dependencies_version;
            $config->set('terminus_dependencies_dir', $terminusDependenciesDir);
            if (file_exists($terminusDependenciesDir . '/vendor/autoload.php')) {
                include_once("$terminusDependenciesDir/vendor/autoload.php");
            } else {
                $dependencies_folder_absent = true;
            }
        }
        $terminus = new static($config, $input, $output);

        if ($dependencies_folder_absent && $terminus->hasPlugins()) {
            $omit_reload_warning = false;

            $input_string = (string) $input;
            $plugin_reload_command_names = [
                'self:plugin:reload',
                'self:plugin:refresh',
                'plugin:reload',
                'plugin:refresh',
            ];
            foreach ($plugin_reload_command_names as $command_name) {
                if (strpos($input_string, $command_name) !== false) {
                    $omit_reload_warning = true;
                    break;
                }
            }

            if (!$omit_reload_warning) {
                $terminus->logger->warning(
                    'Could not load plugins because Terminus was upgraded. ' .
                    'Please run terminus self:plugin:reload to refresh.',
                );
            }
        }
        return $terminus;
    }
}
