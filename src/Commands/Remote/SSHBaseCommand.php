<?php

namespace Pantheon\Terminus\Commands\Remote;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusProcessException;
use Pantheon\Terminus\Helpers\LocalMachineHelper;
use Pantheon\Terminus\Models\Environment;
use Pantheon\Terminus\Models\Site;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Helpers\Utility\TraceId;
use Symfony\Component\Process\Process;

/**
 * Class SSHBaseCommand.
 *
 * Base class for Terminus commands that deal with sending SSH commands.
 *
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
     * @var Environment
     */
    private $environment;
    /**
     * @var Site
     */
    private $site;
    /**
     * @var bool
     */
    protected $progressAllowed;

    /**
     * Define the environment and site properties
     *
     * @param string $site_env The site/env to retrieve in <site>.<env> format
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    protected function prepareEnvironment($site_env)
    {
        $this->site = $this->getSiteById($site_env);
        $this->environment = $this->getEnv($site_env);
    }

    /**
     * progressAllowed sets the field that controls whether a progress bar
     * may be displayed when a program is executed. If allowed, a progress
     * bar will be used in tty mode.
     *
     * @param bool $allowed
     * @return $this
     */
    protected function setProgressAllowed($allowed = true)
    {
        $this->progressAllowed = $allowed;
        return $this;
    }

    /**
     * Executes the command remotely.
     *
     * @param array $command_args
     * @param int $retries Number of times to retry the command on failure.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusProcessException
     */
    protected function executeCommand(array $command_args, int $retries = 0)
    {
        $this->validateEnvironment($this->environment);

        // Retrieve the trace ID from the TraceId class
        $trace_id = TraceId::getTraceId();

        // Prepare the environment variables
        $env_vars = [
            'TRACE_ID' => $trace_id
        ];

        // Get the combined command line
        $command_line = $this->getCommandLine($command_args, $env_vars);

        // Log the trace ID for user visibility only in debug mode
        $this->log()->debug('Trace ID: {trace_id}', ['trace_id' => $trace_id]);

        $attempt = 0;
        $max_attempts = $retries + 1;

        do {
            // Send the combined command line via SSH
            $ssh_data = $this->sendCommandViaSsh($command_line, $env_vars);

            $command_summary = $this->getCommandSummary($command_args);
            // Log the command execution
            $this->log()->notice(
                'Command: {site}.{env} -- {command} [Exit: {exit}] (Attempt {attempt}/{max_attempts})',
                [
                    'site' => $this->site->getName(),
                    'env' => $this->environment->id,
                    'command' => $command_summary,
                    'exit' => $ssh_data['exit_code'],
                    'attempt' => $attempt + 1,
                    'max_attempts' => $max_attempts,
                ]
            );

            // Handle any errors in the command execution
            if ($ssh_data['exit_code'] == 0) {
                return;
            }

            // Check if the failure is permanent
            if ($this->isPermanentFailure($ssh_data['exit_code'])) {
                $this->log()->error('Permanent failure detected. Aborting retries. Exit code: {exit_code}', [
                    'exit_code' => $ssh_data['exit_code']
                ]);
                break;
            }

            $attempt++;
        } while ($attempt < $max_attempts);

        $error_message = sprintf(
            'Command: %s.%s -- %s [Exit: %d] (All attempts failed)',
            $this->site->getName(),
            $this->environment->id,
            $command_summary,
            $ssh_data['exit_code']
        );

        $this->log()->error($error_message);

        throw new TerminusProcessException($ssh_data['output'], [], $ssh_data['exit_code']);
    }

    /**
     * Determines if a failure is permanent based on the exit code.
     *
     * @param int $exit_code
     * @return bool
     */
    protected function isPermanentFailure(int $exit_code): bool
    {
        // Define the exit codes that indicate a permanent failure
        $permanent_failure_exit_codes = [
            2,   // Invalid arguments
            126, // Command cannot execute (permission denied)
            127, // Command not found
        ];

        return in_array($exit_code, $permanent_failure_exit_codes, true);
    }

    /**
     * Sends a command to an environment via SSH.
     *
     * @param string $command The command to be run on the platform
     * @param array $env_vars The environment variables to include in the SSH command
     */
    protected function sendCommandViaSsh($command, array $env_vars = [])
    {
        // Convert env_vars array into a series of key=value strings
        $env_vars_string = '';
        foreach ($env_vars as $key => $value) {
            $env_vars_string .= sprintf('-o SetEnv="%s=%s" ', $key, $value);
        }

        // Construct the SSH command without environment variables in SSH options
        $ssh_command = $this->getConnectionString() . ' ' . $env_vars_string . escapeshellarg($command);

        $this->logger->debug('shell command: {command}', ['command' => $ssh_command]);
        if ($this->getConfig()->get('test_mode')) {
            return $this->divertForTestMode($ssh_command);
        }

        return $this->getContainer()->get(LocalMachineHelper::class)->execute(
            $ssh_command,
            $this->getOutputCallback(),
            $this->progressAllowed
        );
    }

    /**
     * Validates that the environment's connection mode is appropriately set.
     *
     * @param \Pantheon\Terminus\Models\Environment $environment
     */
    protected function validateEnvironment(Environment $environment)
    {
        // Only warn in dev / multidev.
        if ($environment->isDevelopment()) {
            $this->validateConnectionMode($environment->get('connection_mode'));
        }
    }

    /**
     * Validates that the environment is using the correct connection mode.
     *
     * @param string $mode
     */
    protected function validateConnectionMode(string $mode)
    {
        if ((!$this->getConfig()->get('hide_git_mode_warning')) && ($mode == 'git')) {
            $this->log()->warning(
                'This environment is in read-only Git mode. If you want to make changes to the codebase of this site '
                . '(e.g. updating modules or plugins), you will need to toggle into read/write SFTP mode first.'
            );
        }
    }

    /**
     * Outputs a message if Terminus is in test mode and uses it to mock the command's response.
     *
     * @param string $ssh_command
     *
     * @return string[]
     *  Elements are as follows:
     *    string output    The output from the command run
     *    string exit_code The status code returned by the command run
     */
    private function divertForTestMode(string $ssh_command)
    {
        $output = sprintf(
            'Terminus is in test mode. SSH commands will not be sent over the wire.%sSSH Command: %s',
            PHP_EOL,
            $ssh_command
        );
        $container = $this->getContainer();
        if ($container->has('output')) {
            $container->get('output')->write($output);
        }
        return [
            'output' => $output,
            'exit_code' => 0,
        ];
    }

    /**
     * Escapes the command-line args.
     *
     * @param string[] $args
     *   All the arguments to escape.
     *
     * @return array
     */
    private function escapeArguments($args)
    {
        return array_map(
            function ($arg) {
                return $this->escapeArgument($arg);
            },
            $args
        );
    }

    /**
     * Escapes one command-line arg.
     *
     * @param string $arg
     *   The argument to escape.
     *
     * @return string
     */
    private function escapeArgument($arg)
    {
        // Omit escaping for simple args.
        if (preg_match('/^[a-zA-Z0-9_-]*$/', $arg)) {
            return $arg;
        }
        return escapeshellarg($arg);
    }

    /**
     * Returns the first item of the $command_args that is not an option.
     *
     * @param array $command_args
     *
     * @return string
     */
    private function firstArguments($command_args)
    {
        $result = '';
        while (!empty($command_args)) {
            $first = array_shift($command_args);
            if (strlen($first) && $first[0] == '-') {
                return $result;
            }
            $result .= " $first";
        }
        return $result;
    }

    /**
     * Returns the output callback for the process.
     *
     * @return \Closure
     */
    private function getOutputCallback()
    {
        $output = $this->output();
        $stderr = $this->stderr();

        return function ($type, $buffer) use ($output, $stderr) {
            if (Process::ERR === $type) {
                $stderr->write($buffer);
            } else {
                $output->write($buffer);
            }
        };
    }

    /**
     * Returns the command-line args.
     *
     * @param string[] $command_args
     * @param array $env_vars
     *
     * @return array
     *   Elements are as follows:
     *     string command_line The command line string
     *     array env_vars The environment variables
     */
    private function getCommandLine($command_args, $env_vars)
    {
        // Convert env_vars array into a series of key=value strings
        $env_vars_string = '';
        foreach ($env_vars as $key => $value) {
            $env_vars_string .= sprintf('%s=%s ', $key, strtr($value, '-', '_'));
        }

        // Construct the command line with environment variables and the command arguments
        $command_line =
            $env_vars_string
            . implode(" ", $this->escapeArguments(array_merge([$this->command], $command_args)));

        $this->log()->debug('getCommandLine: {command_line}', ['command_line' => $command_line]);

        return $command_line;
    }

    /**
     * Returns a summary of the command that does not include the
     * arguments. This avoids potential information disclosure in
     * CI scripts.
     *
     * @param array $command_args
     *
     * @return string
     */
    private function getCommandSummary($command_args)
    {
        return $this->command . $this->firstArguments($command_args);
    }

    /**
     * Returns the connection string.
     *
     * @return string
     *   SSH connection string.
     */
    private function getConnectionString()
    {
        $sftp = $this->environment->sftpConnectionInfo();
        $command = $this->getConfig()->get('ssh_command');

        return vsprintf(
            '%s -T %s@%s -p %s -o "StrictHostKeyChecking=no" -o "AddressFamily inet"',
            [$command, $sftp['username'], $this->lookupHostViaAlternateNameserver($sftp['host']), $sftp['port']]
        );
    }

    /**
     * Uses an alternate name server, if selected, to look up the provided hostname.
     * Set nameserver via environment variable TERMINUS_ALTERNATE_NAMESERVER.
     * Allows 'terminus drush' and 'terminus wp' to work on a sandbox.
     *
     * @param string $host Hostname to look up, e.g. 'appserver.dev.91275f92-eeae-4cea-89a5-9d0593dff16c.drush.in'
     *
     * @return string
     */
    private function lookupHostViaAlternateNameserver(string $host): string
    {
        $alternateNameserver = $this->getConfig()->get('alternate_nameserver');
        if (!$alternateNameserver || !class_exists('\Net_DNS2_Resolver')) {
            return $host;
        }

        // Net_DNS2 requires an IP address for the nameserver; look up the IP from the name.
        $nameserver = gethostbyname($alternateNameserver);
        $r = new \Net_DNS2_Resolver(array('nameservers' => [$nameserver]));
        $result = $r->query($host, 'A');
        foreach ($result->answer as $index => $o) {
            return $o->address;
        }

        return $host;
    }
}
