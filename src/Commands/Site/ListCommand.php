<?php

namespace Pantheon\Terminus\Commands\Site;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\StructuredListTrait;

class ListCommand extends SiteCommand
{
    use StructuredListTrait;

    private const OPTION_OWNER_ME = 'me';
    private const OPTION_ORG_ALL = 'all';

    /**
     * Displays the list of sites accessible to the currently logged-in user.
     *
     * @authorize
     * @filter-output
     *
     * @command site:list
     * @aliases sites
     *
     * @field-labels
     *     name: Name
     *     label: Label
     *     id: ID
     *     plan_name: Plan
     *     framework: Framework
     *     region: Region
     *     owner: Owner
     *     created: Created
     *     memberships: Memberships
     *     frozen: Is Frozen?
     *     last_frozen_at: Date frozen
     * @default-fields name,id,plan_name,framework,region,owner,created,memberships,frozen
     *
     * @param array $options
     *
     * @return RowsOfFields
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Pantheon\Terminus\Exceptions\TerminusNotFoundException
     *
     * @option name DEPRECATED Name filter
     * @option org DEPRECATED Organization filter; "all" or an organization's name, label, or ID
     * @option owner Owner filter; "me" or user UUID
     * @option plan DEPRECATED Plan filter; filter by the plan's label
     * @option team Team-only filter
     * @option string $upstream Upstream name to filter
     *
     * @usage Displays the list of all sites accessible to the currently logged-in user.
     * @usage --name=<regex> Displays a list of accessible sites with a name that matches <regex>.
     * @usage --org=<org> Displays a list of accessible sites associated with the <org> organization.
     * @usage --org=all Displays a list of accessible sites associated with any organization of which the currently logged-in is a member.
     * @usage --owner=<user> Displays the list of accessible sites owned by the user with UUID <user>.
     * @usage --owner=me Displays the list of sites owned by the currently logged-in user.
     * @usage --plan=<plan> Displays the list of sites with a plan of this name
     * @usage --team Displays the list of sites of which the currently logged-in user is a member of the team.
     * @usage --upstream=<upstream> Displays the list of sites with the upstream having UUID <upstream>.
     */
    public function index($options = [
        'name' => null,
        'org' => self::OPTION_ORG_ALL,
        'owner' => null,
        'plan' => null,
        'team' => false,
        'upstream' => null,
    ])
    {
        $user = $this->session()->getUser();
        $this->sites()->fetch(
            [
                'org_id' => self::OPTION_ORG_ALL !== $options['org']
                    ? $user->getOrganizationMemberships()->get($options['org'])->getOrganization()->id
                    : null,
                'team_only' => $options['team'],
            ]
        );

        if (null !== $options['name']) {
            $this->sites->filterByName($options['name']);
        }
        if (null !== $options['plan']) {
            $this->sites->filterByPlanName($options['plan']);
        }
        if (null !== $options['upstream']) {
            $this->sites->filterByUpstream($options['upstream']);
        }

        switch ($options['owner']) {
            case null:
                break;
            case self::OPTION_OWNER_ME:
                $this->sites->filterByOwner($user->id);
                break;
            default:
                $this->sites->filterByOwner($options['owner']);
        }

        return $this->getRowsOfFields($this->sites);
    }
}
