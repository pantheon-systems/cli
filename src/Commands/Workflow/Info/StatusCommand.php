<?php

namespace Pantheon\Terminus\Commands\Workflow\Info;

use Consolidation\OutputFormatters\StructuredData\PropertyList;

/**
 * Class StatusCommand
 * @package Pantheon\Terminus\Commands\Workflow\Info
 */
class StatusCommand extends InfoBaseCommand
{
    /**
     * Show status information about a workflow
     *
     * @authorize
     *
     * @command workflow:info:status
     *
     * @field-labels
     *   id: Workflow ID
     *   env: Environment
     *   workflow: Workflow
     *   user: User
     *   status: Status
     *   started_at: Started At
     *   finished_at: Finished At
     *   time: Time Elapsed
     * @return PropertyList
     *
     * @param string $site_id Name or ID of the site that the workflow is part of
     * @option string $id UUID of the workflow to show
     *
     * @usage terminus workflow:info:operations <site> <workflow>
     *   Shows the status of the workflow identified by <workflow> found on <site>
     * @usage terminus workflow:info:operations <site>
     *   Shows the status of the most recent workflow found on <site>
     */
    public function status($site_id, $options = ['id' => null,])
    {
        $workflow_data = $this->getWorkflow($site_id, $options['id'])->serialize();
        unset($workflow_data['operations']);
        return new PropertyList($workflow_data);
    }
}
