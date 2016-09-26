<?php

namespace Terminus\Commands;

/**
 * @command wp
 */
class WpCommand extends CommandWithSSH
{
  /**
   * @inheritdoc
   */
    protected $client = 'WP-CLI';

  /**
   * @inheritdoc
   */
    protected $command = 'wp';

  /**
   * @inheritdoc
   */
    protected $unavailable_commands = [
    'db' => '',
    ];

  /**
   * Invoke `wp` commands on a Pantheon development site
   *
   * <commands>
   * : The WP-CLI command you intend to run with its arguments, in quotes
   *
   * [--site=<site>]
   * : The name (DNS shortname) of your site on Pantheon
   *
   * [--env=<environment>]
   * : Your Pantheon environment. Default: dev
   *
   */
    public function __invoke($args, $assoc_args)
    {
        parent::__invoke($args, $assoc_args);
        $result = $this->environment->sendCommandViaSsh($this->ssh_command);
        $this->output()->outputDump($result['output']);
        exit($result['exit_code']);
    }
}
