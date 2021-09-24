<?php

namespace Pantheon\Terminus\Commands\Auth;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Models\TerminusModel;

/**
 * Class LoginCommand.
 *
 * @package Pantheon\Terminus\Commands\Auth
 */
class LoginCommand extends TerminusCommand
{

    public static $HELP_TEXT = [
        "*******************************************************************************",
        "* THIS IS AN EARLY VERSION OF TERMINUS 3.0. NOT ALL THE COMMANDS ARE WORKING  *",
        "* AND IT'S NOT 100% COMPATIBLE WITH PHP 8 BUT WE'RE GETTING THERE.            *",
        "* If you find a bug you think needs to be addressed, please add the bug to    *",
        "* terminus issue queue: https://github.com/pantheon-systems/terminus/issues   *",
        "*******************************************************************************",
    ];

    /**
     * Logs in a user to Pantheon.
     *
     * @command auth:login
     * @aliases login
     *
     * @option machine-token Grants access for a user and is saved for future logins
     * @option email Uses an existing machine token for this user
     *
     * @usage --machine-token=<machine_token> Logs in a user granted the machine token <machine_token>.
     * @usage Logs in a user with a previously saved machine token.
     * @usage --email=<email> Logs in a user with a previously saved machine token belonging to <email>.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function logIn(array $options = ['machine-token' => null, 'email' => null,]): void
    {
        $tokens = $this->session()->getTokens();

        if (isset($options['machine-token'])) {
            try {
                $token = $tokens->get($options['machine-token']);
                $this->processLogIn($token);
                return;
            } catch (\Exception $e) {
                $this->log()->notice('Logging in via machine token.');
                $tokens->create($options['machine-token']);
            }
        }

        if (isset($options['email'])) {
            $token = $tokens->get($options['email']);
            $this->processLogIn($token);
            return;
        }

        $all_tokens = $tokens->all();
        switch (count($all_tokens)) {
            case 0:
                throw new TerminusException(
                    "Please visit the dashboard to generate a machine token:\n{url}",
                    ['url' => $this->getMachineTokenCreationURL(),]
                );
            case 1:
                $token = array_shift($all_tokens);
                $this->log()->notice('Found a machine token for {email}.', ['email' => $token->get('email'),]);
                $this->processLogIn($token);
                break;
            default:
                throw new TerminusException(
                    "Tokens were saved for the following email addresses:\n{tokens}\nYou may log in via `terminus"
                    . " auth:login --email=<email>`, or you may visit the dashboard to generate a machine"
                    . " token:\n{url}",
                    ['tokens' => implode("\n", $tokens->ids()), 'url' => $this->getMachineTokenCreationURL(),]
                );
        }
    }

    /**
     * Processes the login.
     *
     * @param TerminusModel $token
     */
    private function processLogIn(TerminusModel $token): void
    {
        /** @var $token \Pantheon\Terminus\Models\SavedToken */
        $token->logIn();
        $this->log()->notice('Logged in via machine token.');

        if (static::$HELP_TEXT) {
            $this->log()->notice(
                PHP_EOL . join(PHP_EOL, static::$HELP_TEXT)
            );
        }
    }

    /**
     * Generates the URL string for where to create a machine token.
     *
     * @return string
     */
    private function getMachineTokenCreationURL()
    {
        return vsprintf(
            '%s://%s/machine-token/create/%s',
            [
                $this->config->get('dashboard_protocol'),
                $this->config->get('dashboard_host'),
                gethostname(),
            ]
        );
    }
}
