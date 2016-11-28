<?php

namespace Pantheon\Terminus\Commands\Site\Org;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

/**
 * Class ListCommand
 * @package Pantheon\Terminus\Commands\Site\Org
 */
class ListCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * List the supporting organizations for the given site
     *
     * @authorize
     *
     * @command site:org:list
     * @aliases site:orgs
     *
     * @field-labels
     *   org_name: Name
     *   org_id: ID
     * @return RowsOfFields
     *
     * @param string $site_id The name or UUID of the site to list the supporting organizations of
     *
     * @usage terminus site:org:list <site>
     *   Displays a list of the supporting organizations associated with <site>
     */
    public function listOrgs($site_id)
    {
        $orgs = array_map(
            function ($site) {
                return $site->serialize();
            },
            $this->getSite($site_id)->getOrganizationMemberships()->all()
        );

        if (empty($orgs)) {
            $this->log()->notice('This site has no supporting organizations.');
        }
        return new RowsOfFields($orgs);
    }
}
