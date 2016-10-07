<?php

namespace Pantheon\Terminus\Commands\Site;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class ListCommand extends SiteCommand
{
    /**
     * Looks up a site name
     *
     * @command site:list
     * @alias sites
     *
     * @field-labels
     *   name: Name
     *   id: ID
     *   service_level: Service Level
     *   framework: Framework
     *   owner: Owner
     *   created: Created
     *   memberships: Memberships
     * @option team Filter for sites you are a team member of
     * @option owner Filter for sites a specific user owns. Use "me" for your own user
     * @option org Filter sites you can access via the organization. Use "all" to get all.
     * @option name Filter sites you can access via name.
     * @usage terminus site:list
     *   * Responds with list of every site you can access
     *   * Responds with "You have no sites." if you have no sites
     * @usage terminus site:list --team
     *   * Responds with a list of sites you are a team member of
     *   * Responds with a notice stating no sites match criteria if none exist
     * @usage terminus site:list --owner=<uuid>
     *   * Responds with a list of sites owned by the user with the given UUID
     *   * Responds with a notice stating no sites match criteria if none exist
     * @usage terminus site:list --owner=me
     *   * Responds with a list of sites the logged-in user owns
     *   * Responds with a notice stating no sites match criteria if none exist
     * @usage terminus site:list --org=<id|name>
     *   * Responds with a list of sites inside the organization(s) indicated
     *   * Responds with a notice stating no sites match criteria if none exist
     * @usage terminus site:list --org=all
     *   * Responds with a list of sites belonging to organization you are a member of
     *   * Responds with a notice stating no sites match criteria if none exist
     * @usage terminus site:list --name=<regex>
     *   * Responds with a list of sites you have access to by name
     *   * Responds with a notice stating no sites match criteria if none exist
     * @return RowsOfFields
     */
    public function index($options = ['team' => false, 'owner' => null, 'org' => null, 'name' => null,])
    {
        $sites = $this->sites();
        $sites->fetch(
            [
                'org_id' => $options['org'],
                'team_only' => $options['team'],
            ]
        );

        if (!is_null($name = $options['name'])) {
            $sites->filterByName($name);
        }
        if (!is_null($owner = $options['owner'])) {
            if ($owner == 'me') {
                $owner = $this->session()->getUser()->id;
            }
            $sites->filterByOwner($owner);
        }

        $all_sites = array_map(
            function ($site) {
                $site_data = $site->serialize();
                return [
                    'name'          => $site_data['name'],
                    'id'            => $site_data['id'],
                    'service_level' => $site_data['service_level'],
                    'framework'     => $site_data['framework'],
                    'owner'         => $site_data['owner'],
                    'created'       => $site_data['created'],
                    'memberships'   => implode(',', $site->memberships),
                ];
            },
            $sites->all()
        );

        if (empty($all_sites)) {
            $this->log()->notice('You have no sites.');
        }

        return new RowsOfFields($all_sites);
    }
}
