<?php

namespace Pantheon\Terminus\Models;

use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class Workflow
 *
 * @package Pantheon\Terminus\Models
 */
class WorkflowLog extends TerminusModel
{
    /**
     * @var string
     */
    public const PRETTY_NAME = 'workflow log entry';
    /**
     * @var int
     */
    protected const REFRESH_INTERVAL = 15;
    /**
     * @var string|mixed
     */
    public ?string $kind;
    /**
     * @var WorkflowLogActor
     */
    public WorkflowLogActor $actor;
    /**
     * @var WorkflowLogInfo
     */
    public WorkflowLogInfo $workflow;
    /**
     * @var string
     */
    protected $url = 'sites/{site_id}/logs/workflows/{id}';

    /**
     * @param $attributes
     * @param array $options
     * @throws TerminusException
     */
    public function __construct($attributes = null, array $options = [])
    {
        if (isset($options['collection'])) {
            $this->collection = $options['collection'];
        }
        try {
            parent::__construct($attributes, $options);
            $this->actor = new WorkflowLogActor($this->get('actor'));
            $this->workflow = new WorkflowLogInfo($this->get('workflow'));
            $this->kind = $this->get('kind');
            $this->id = $this->workflow->id ?? '';
        } catch (\Exception $e) {
            throw new TerminusException(
                "Exception unpacking workflow Logs: {message}",
                ['message' => $e->getMessage()]
            );
        }
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return [
            "id" => $this->workflow->id,
            "env" => $this->workflow->environment,
            "workflow" => $this->workflow->type,
            'user' => $this->actor->name,
            'status' => $this->workflow->status,
            'finished' => $this->isFinished() ? "Yes" : "No",
            // if this is not finished, then success should be "?"
            'successful' => ($this->isFinished() === false) ? '?' : ($this->isSuccessful() ? 'Yes' : 'No'),
            'started_at' => date('Y-m-d H:i:s', round($this->workflow->started_at)),
            'finished_at' => date('Y-m-d H:i:s', round($this->workflow->finished_at)),
            'time' => $this->workflow->finished_at - $this->workflow->started_at,
        ];
    }

    /**
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->workflow->isFinished();
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->workflow->isSuccessful();
    }


    /**
     * Wait for the workflow to complete and return success/failure as bool.
     *
     * @return bool
     * @throws TerminusException
     */
    public function waitForComplete($max = 180): bool
    {
        $start = time();
        $this->workflow->fetch();
        while (!$this->isFinished() && (time() - $start) < $max) {
            sleep(self::REFRESH_INTERVAL);
            $this->workflow->fetch();
        }
        return $this->isSuccessful();
    }

    /**
     * @param $name
     * @return null
     */
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
        if (isset($this->workflow->{$name})) {
            return $this->workflow->{$name};
        }
        if (isset($this->actor->{$name})) {
            return $this->actor->{$name};
        }
        return null;
    }
}
