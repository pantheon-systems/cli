<?php

namespace Pantheon\Terminus\Commands\Site\Team;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

/**
 * Class ListCommand
 * @package Pantheon\Terminus\Commands\Site\Team
 */
class ListCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * List team members for a site
     *
     * @authorize
     *
     * @command site:team:list
     * @aliases site:team
     *
     * @field-labels
     *   first: First name
     *   last: Last name
     *   email: Email
     *   role: Role
     *   uuid: User ID
     * @return RowsOfFields
     *
     * @param string $site_id Site name to list team members for.
     *
     * @usage terminus site:team:list <site>
     *   Lists team members for <site>
     */
    public function teamList($site_id)
    {
        $site = $this->getSite($site_id);
        $user_memberships = $site->getUserMemberships()->all();
        $data = [];
        foreach ($user_memberships as $user_membership) {
            $user = $user_membership->get('user');
            $data[] = array(
                'first' => $user->profile->firstname,
                'last'  => $user->profile->lastname,
                'email' => $user->email,
                'role'  => $user_membership->get('role'),
                'uuid'  => $user->id,
            );
        }
        return new RowsOfFields($data);
    }
}
