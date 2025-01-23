<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Put this file in $HOME/.drush/pantheon/Commands/PantheonPolicyCommands.php
 * and load it via:
 *
 *     drush --include=$HOME/.drush/pantheon
 *
 * Alternately, add the path to $HOME/.drush/drush.yml:
 *
 *     drush:
 *       paths:
 *         include:
 *           - '${env.home}/.drush/pantheon'
 */

class PantheonAliasPolicyCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Check to see if the current alias is a Pantheon alias. If so, we will
     * validate whether or not the environment exists for that alias.
     *
     * @hook pre-init *
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Consolidation\AnnotatedCommand\AnnotationData $annotationData
     */
    public function alter(InputInterface $input, AnnotationData $annotationData)
    {
        $self = $this->siteAliasManager()->getSelf();
        if ($self->isRemote()) {
            $host = $self->get('host');

            if (!(preg_match('#^appserver\..*\.drush\.in$#', $host))) {
                return;
            }

            $ip = gethostbyname($host);

            // If the return value of gethostbyname equals its input parameter,
            // that indicates failure.
            if ($host == $ip) {
                $aliasName = $self->name();
                throw new \Exception("The alias $aliasName refers to a multidev environment that does not exist.");
            }
        }
    }
}
