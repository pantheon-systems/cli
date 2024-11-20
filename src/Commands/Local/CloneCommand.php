<?php

namespace Pantheon\Terminus\Commands\Local;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Config\ConfigAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusAlreadyExistsException;
use Pantheon\Terminus\Helpers\LocalMachineHelper;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Robo\Contract\ConfigAwareInterface;

/**
 * Class CloneCommand.
 *
 * @package Pantheon\Terminus\Commands\Local
 */
class CloneCommand extends TerminusCommand implements SiteAwareInterface, ConfigAwareInterface
{
    use SiteAwareTrait;
    use ConfigAwareTrait;

    /**
     * CLone a copy of the site code into $HOME/pantheon-local-copies
     *
     * @authorize
     *
     * @command local:clone
     * @aliases lc
     *
     * @param string $site_id The name or UUID of the site
     * @param array $options
     *
     * @return string
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     *
     * @option site_dir Custom directory for the local copy. By default, the site name is used
     * @option override Override the local copy if exists
     * @option branch The branch to clone. Default is master
     *
     * @usage <site> Clone's a local copy into "$HOME/pantheon-local-copies"
     */
    public function clone(string $site_id, array $options = ['site_dir' => null, 'override' => null, 'branch' => null]): string
    {
        $site = $this->getSiteById($site_id);
        $env = $site->getEnvironments()->get('dev');

        $gitUrl = $env->connectionInfo()['git_url'] ?? null;
        $localCopyDir = $site->getLocalCopyDir($options['site_dir'] ?? null);

        $devBranch = 'master';
        if ( ! empty( $options['branch'] ) ) {
            $devBranch = $options['branch'];
        }

        try {
            /** @var \Pantheon\Terminus\Helpers\LocalMachineHelper $localMachineHelper */
            $localMachineHelper = $this->getContainer()->get(LocalMachineHelper::class);
            $localMachineHelper->cloneGitRepository(
                $gitUrl,
                $localCopyDir,
                $options['override'] ?? false,
                $devBranch
            );
        } catch (TerminusAlreadyExistsException $e) {
            $this->logger->notice(
                sprintf('The local copy of the site %s already exists in %s', $site->getName(), $localCopyDir)
            );
        }

        return $localCopyDir;
    }
}
