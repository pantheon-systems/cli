<?php

namespace Pantheon\Terminus\Commands\Org;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Friends\RowsOfFieldsInterface;
use Pantheon\Terminus\Friends\RowsOfFieldsTrait;

/**
 * Class ListCommand
 * @package Pantheon\Terminus\Commands\Org
 */
class ListCommand extends TerminusCommand implements RowsOfFieldsInterface
{
    use RowsOfFieldsTrait;

    /**
     * Displays the list of organizations.
     *
     * @authorize
     *
     * @command org:list
     * @aliases orgs
     *
     * @field-labels
     *     id: ID
     *     name: Name
     *     label: Label
     * @return RowsOfFields
     *
     * @usage Displays the list of organizations.
     */
    public function listOrgs()
    {
        return $this->getRowsOfFields(
            $this->session()->getUser()->getOrganizationMemberships(),
            ['message' => 'You are not a member of any organizations.',]
        );
    }
}
