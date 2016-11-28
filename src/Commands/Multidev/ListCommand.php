<?php

namespace Pantheon\Terminus\Commands\Multidev;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

class ListCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * List the multidev environments belonging to the given site
     *
     * @authorize
     *
     * @command multidev:list
     * @aliases multidevs
     *
     * @field-labels
     *   id: ID/Name
     *   created: Created
     *   domain: Domain
     *   onserverdev: OnServer Dev?
     *   locked: Locked?
     *   initialized: Initialized?
     * @return RowsOfFields
     *
     * @param string $site_name Name of the site to list multidev environments of
     *
     * @usage terminus multidev:list awesome-site
     *   Display a list of multidev environments on awesome-site
     */
    public function listMultidevs($site_name)
    {
        $envs = array_map(
            function ($environment) {
                return $environment->serialize();
            },
            $this->sites->get($site_name)->getEnvironments()->multidev()
        );

        if (empty($envs)) {
            $this->log()->warning('You have no multidev environments.');
        }

        // Return the output data.
        return new RowsOfFields($envs);
    }
}
