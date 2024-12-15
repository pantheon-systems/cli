<?php

namespace Pantheon\Terminus\Helpers;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Consolidation\Config\ConfigInterface;
use Pantheon\Terminus\Config\ConfigAwareTrait;
use Pantheon\Terminus\Config\DefaultsConfig;
use Pantheon\Terminus\Config\DotEnvConfig;
use Pantheon\Terminus\Config\EnvConfig;
use Pantheon\Terminus\Config\YamlConfig;
use Robo\Common\IO;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class CommandCoverageReport
 *
 * @package Pantheon\Terminus\Helpers
 */
class CommandCoverageReport implements ConfigAwareInterface, IOAwareInterface
{
    use ConfigAwareTrait;
    use IO;

    public static $STATUS_ICON = [
        '-1' => "❌",
        '0' => '✅',
        '1' => '💩',
        '2' => '💩',
        '3' => '❌',
        '4' => '🤮',
        '5' => '❓'
    ];

    /**
     * @var string
     */
    public static $HELP_TEXT = [
        "********************************************************************************",
        "* Once upon a time, terminus had unit tests and those unit tests passed.       *",
        "* Somewhere in it’s history those unit tests were disabled and no longer       *",
        "* worked. For v2.6 we have a single functional test running and because there  *",
        "* hasn’t been much development in Terminus this has sufficed. With this much   *",
        "* change in Terminus we can’t let that continue. In order to have confidence   *",
        "* in this release and move forward with development of the product, We need a  *",
        "* full suite of functional tests.  I’ve written a bunch of new tests and       *",
        "* committed a coverage page keeping track of what’s been written,              *",
        "* what’s passing and what is lacking. This page should be updated with every   *",
        "* commit.                                                                      *",
        "********************************************************************************",
    ];



    public function __construct(ConfigInterface $config, ConsoleIO $io)
    {
        $this->setConfig($config);
        $this->io = $io;
    }

    public static function factory(?OutputInterface $output = null)
    {
        $input = new ArgvInput($_SERVER['argv']);
        $output = new ConsoleOutput();
        $config = new DefaultsConfig();
        $config->extend(new YamlConfig($config->get('root') . '/config/constants.yml'));
        $config->extend(new YamlConfig($config->get('user_home') . '/.terminus/config.yml'));
        $config->extend(new DotEnvConfig(getcwd()));
        $config->extend(new EnvConfig());
        return new static($config, new ConsoleIO($input, $output));
    }


    /**
     *
     */
    public function getReport($template = 'README.twig'): string
    {
        $total_tests = 0;
        $passing_tests = 0;
        $missing_tests = 0;
        $context = $this->getConfig()->exportAll()['process'] ?? [];
        $commandsFolder = $context['root'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Commands';
        $commands = static::getCommands($commandsFolder, "\\Pantheon\\Terminus\\Commands");
        $parsedTestResults = static::getTestResults();
        $loader = new FilesystemLoader($context['root'] . DIRECTORY_SEPARATOR . 'templates');
        $twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);
        $twig->addExtension(new \Twig\Extension\DebugExtension());
        $twig->getExtension(\Twig\Extension\EscaperExtension::class)
            ->setEscaper('raw', 'utf8_decode');
        $factory = new AnnotatedCommandFactory();
        foreach ($commands as $file => $className) {
            $reflector = new \ReflectionClass($className);
            if (!$reflector->isAbstract()) {
                $total_tests += 1;
                $classObject = new $className();

                $commandInfo = $factory->getCommandInfoListFromClass($classObject);
                $commandName = static::classToCommandName($className);
                $groupNameSafe = static::classToGroupName($className);
                if (is_array($commandInfo)) {
                    $commandInfo = array_shift($commandInfo);
                }
                $test_result = ($parsedTestResults[$groupNameSafe][$commandName] ?? 5);
                if ($test_result == 5) {
                    $missing_tests += 1;
                }
                if ($commandInfo instanceof CommandInfo) {
                    if ($test_result == 0) {
                        $passing_tests += 1;
                    }
                    $command = $commandInfo->getAnnotation('command');
                    [$group] = explode(':', $command);
                    $context['commands'][$commandName] = [
                        'groupNameSafe' => $groupNameSafe,
                        'commandName' => $commandName,
                        'group' => $group,
                        'command' => $command,
                        'status' => static::$STATUS_ICON[$test_result],
                        'description' => explode(
                            PHP_EOL,
                            wordwrap(
                                html_entity_decode($commandInfo->getDescription()),
                                60,
                                PHP_EOL
                            )
                        )
                    ];
                }
            }
        }
        ksort($context['commands']);
        $context['total_tests'] = $total_tests;
        $context['passing_tests'] = $passing_tests;
        $context['missing_tests'] = $missing_tests;
        return $twig->render($template, $context);
    }

    /**
     * Discovers command classes using CommandFileDiscovery
     *
     * @param string[] $options Elements as follow
     *        string path      The full path to the directory to search for commands
     *        string namespace The full namespace associated with given the command directory
     *
     * @return TerminusCommand[] An array of TerminusCommand instances
     */
    public static function getCommands($path, $baseNamespace)
    {
        $discovery = new CommandFileDiscovery();
        $discovery->setSearchPattern('*Command.php')->setSearchLocations([]);
        return $discovery->discover($path, $baseNamespace);
    }

    /**
     * @param string $file
     */
    public static function getTestResults(string $file = "reports/logfile.xml")
    {
        $toReturn = [];
        $json_string = json_encode(simplexml_load_file(
            static::getRootDir() . DIRECTORY_SEPARATOR . $file
        ));
        $testReport = json_decode($json_string, true);
        foreach ($testReport['test'] as $test) {
            if (isset($test['covers']['@attributes']) ?? false) {
                $test['covers'] = [$test['covers']];
            }
            foreach ($test['covers'] ?? [] as $coveredClass) {
                $commandName = static::classToCommandName($coveredClass['@attributes']['target']);
                $commandGroup = static::classToGroupName($coveredClass['@attributes']['target']);
                $toReturn[$commandGroup][$commandName] = $test['@attributes']['status'];
            }
        }
        return $toReturn;
    }

    /**
     * @return string
     */
    public static function getRootDir()
    {
        return dirname(__FILE__, 3);
    }

    /**
     * @param $class
     *
     * @return string
     */
    public static function classToCommandName($class): string
    {
        $exploded_class = explode("\\Commands\\", $class);
        $command_name = end($exploded_class);
        $command_short = str_replace(
            "Command",
            "",
            $command_name ?? ''
        );
        return $command_short;
    }


    /**
     * @param $class
     *
     * @return string
     */
    public static function classToGroupName($class): string
    {
        $exploded_class = explode("\\Commands\\", $class);
        $exploded_again = explode("\\", $exploded_class[1]);
        return strtolower($exploded_again[0] ?? '');
    }

    /**
     * Parses PHP internal documentation into chunks
     *
     * @param string $doc_string The raw doc string from the PHP file
     * @return array
     */
    public function parseDocString(string $doc_string)
    {
        $exploded_docs = explode("\n", $doc_string);
        $lines         = array();
        foreach ($exploded_docs as $doc_line) {
            $line = trim(str_replace(array('/**', '*/', '*'), '', trim($doc_line)));
            if (!empty($line)) {
                $lines[] = $line;
            }
        }
        $parsed_doc = ['description' => [], 'param' => [], 'return' => [], 'throws' => [],];
        $current    = 'description';
        do {
            $line = array_shift($lines);
            if ($line[0] == '@') {
                $breakdown = explode(' ', $line);
                $current   = substr($breakdown[0], 1);
                unset($breakdown[0]);
                if ($current == 'param' || $current == 'return') {
                    if (substr($breakdown[1], 0, 1) != '[') {
                        $breakdown[1] = '[' . $breakdown[1] . ']';
                    }
                }
                $line = implode(' ', $breakdown);
            } elseif ($current != 'description') {
                $line = "-$line";
            }
            $parsed_doc[$current][] = $line;
        } while (!empty($lines));
        return $parsed_doc;
    }

    public function __toString()
    {
        return $this->getReport();
    }
}
