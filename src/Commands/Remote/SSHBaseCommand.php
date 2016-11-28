<?php

namespace Pantheon\Terminus\Commands\Remote;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class SSHBaseCommand
 * Base class for Terminus commands that deal with sending SSH commands
 * @package Pantheon\Terminus\Commands\Remote
 */
abstract class SSHBaseCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * @var string Name of the command to be run as it will be used on server
     */
    protected $command = '';
    /**
     * @var string[] A hash of commands which do not work using Terminus.
     *               The key is the native command, and the value is the Terminus equivalent which is optional.
     */
    protected $unavailable_commands = [];
    /**
     * @var array
     */
    protected $valid_frameworks = [];
    /**
     * @var Site
     */
    private $site;
    /**
     * @var Environment
     */
    private $environment;

    /**
     * Define the environment and site properties
     *
     * @param string $site_env_id The site/env to retrieve in <site>.<env> format
     */
    protected function prepareEnvironment($site_env_id)
    {
        list($this->site, $this->environment) = $this->getSiteEnv($site_env_id);
    }

    /**
     * Execute the command remotely
     *
     * @param array $command_args
     * @return string
     * @throws TerminusException
     */
    protected function executeCommand(array $command_args)
    {
        $output = '';

        $this->validateEnvironment($this->site, $this->environment);

        if ($this->validateCommand($command_args)) {
            $command_line = $this->getCommandLine($command_args);

            $result = $this->environment->sendCommandViaSsh($command_line);
            $output = $result['output'];
            $exit   = $result['exit_code'];

            $this->log()->info('Command: {site}.{env} -- {command} [Exit: {exit}]', [
                'site'    => $this->site->get('name'),
                'env'     => $this->environment->id,
                'command' => escapeshellarg($command_line),
                'exit'    => $exit,
            ]);

            if ($exit != 0) {
                throw new TerminusException($output);
            }
        }

        return rtrim($output);
    }

    /**
     * Validates the command as available
     *
     * @param array $command
     * @return boolean
     */
    protected function validateCommand(array $command)
    {
        $is_valid = true;
        foreach ($command as $element) {
            if (isset($this->unavailable_commands[$element])) {
                $is_valid       = false;
                $message        = "That command is not available via Terminus. ";
                $message        .= "Please use the native {command} command.";
                $interpolations = ['command' => $this->command];
                if (!empty($alternative = $this->unavailable_commands[$element])) {
                    $message .= " Hint: You may want to try `{suggestion}`.";
                    $interpolations['suggestion'] = "terminus $alternative";
                }
                $this->log()->error($message, $interpolations);
            }
        }

        return $is_valid;
    }

    /**
     * Validates that the environment's connection mode is appropriately set
     *
     * @param Site $site
     * @param Environment $environment
     */
    protected function validateEnvironment($site, $environment)
    {
        $this->validateConnectionMode($environment->get('connection_mode'));
        $this->validateFramework($site->get('framework'));
    }

    /**
     * Validates that the environment is using the correct connection mode
     *
     * @param string $mode
     */
    protected function validateConnectionMode($mode)
    {
        if ($mode == 'git') {
            $this->log()->notice(
                "This environment is in read-only Git mode. "
                . "If you want to make changes to the codebase of this site "
                . "(e.g. updating modules or plugins), "
                . "you will need to toggle into read/write SFTP mode first."
            );
        }
    }

    /**
     * Validates the framework being used
     *
     * @param string $framework
     * @throws TerminusException
     */
    protected function validateFramework($framework)
    {
        if (!in_array($framework, $this->valid_frameworks)) {
            throw new TerminusException(
                "The {command} command is only available on sites running {frameworks}. "
                ."The framework for this site is {framework}.",
                [
                    'command'    => $this->command,
                    'frameworks' => implode(", ", $this->valid_frameworks),
                    'framework'  => $framework,
                ]
            );
        }
    }

    /**
     * Gets the command-line args
     *
     * @param string $command_args
     * @return string
     */
    private function getCommandLine($command_args)
    {
        array_unshift($command_args, $this->command);

        return implode(" ", $command_args);
    }
}
