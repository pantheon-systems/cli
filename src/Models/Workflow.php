<?php

namespace Pantheon\Terminus\Models;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Pantheon\Terminus\Session\SessionAwareInterface;
use Pantheon\Terminus\Session\SessionAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class Workflow
 * @package Pantheon\Terminus\Models
 */
class Workflow extends TerminusModel implements ContainerAwareInterface, SessionAwareInterface
{
    use ContainerAwareTrait;
    use SessionAwareTrait;

    /**
     * @var mixed
     */
    protected $owner;

    /**
     * @var Environment
     */
    private $environment;
    /**
     * @var Organization
     */
    private $organization;
    /**
     * @var Site
     */
    private $site;
    /**
     * @var User
     */
    private $user;

    // @TODO: Make this configurable.
    const POLLING_PERIOD = 3;

    /**
     * Object constructor
     *
     * @param object $attributes Attributes of this model
     * @param array $options Options with which to configure this model
     * @return Workflow
     */
    public function __construct($attributes = null, array $options = [])
    {
        parent::__construct($attributes, $options);
        $this->owner = null;
        if (isset($options['collection'])) {
            $this->owner = $options['collection']->getOwnerObject();
        } elseif (isset($options['environment'])) {
            $this->owner = $options['environment'];
        } elseif (isset($options['organization'])) {
            $this->owner = $options['organization'];
        } elseif (isset($options['site'])) {
            $this->owner = $options['site'];
        } elseif (isset($options['user'])) {
            $this->owner = $options['user'];
        }
    }

    /**
     * Get the URL for this model
     *
     * @return string
     */
    public function getUrl()
    {
        if (!empty($this->url)) {
            return $this->url;
        }

        // Determine the url based on the workflow owner.
        switch (get_class($this->owner)) {
            case 'Pantheon\Terminus\Models\Environment':
                $this->environment = $this->owner;
                $this->url = sprintf(
                    'sites/%s/workflows/%s',
                    $this->environment->site->id,
                    $this->id
                );
                break;
            case 'Pantheon\Terminus\Models\Organization':
                $this->organization = $this->owner;
                $this->url = sprintf(
                    'users/%s/organizations/%s/workflows/%s',
                    // @TODO: This should be passed in rather than read from the current session.
                    $this->session()->getUser()->id,
                    $this->organization->id,
                    $this->id
                );
                break;
            case 'Pantheon\Terminus\Models\Site':
                $this->site = $this->owner;
                $this->url = sprintf(
                    'sites/%s/workflows/%s',
                    $this->site->id,
                    $this->id
                );
                break;
            case 'Pantheon\Terminus\Models\User':
                $this->user = $this->owner;
                $this->url = sprintf(
                    'users/%s/workflows/%s',
                    $this->user->id,
                    $this->id
                );
                break;
        }
        return $this->url;
    }

    /**
     * Re-fetches workflow data hydrated with logs
     *
     * @return Workflow
     */
    public function fetchWithLogs()
    {
        $options = ['query' => ['hydrate' => 'operations_with_logs',],];
        $this->fetch($options);
        return $this;
    }

    /**
     * Returns the status of this workflow
     *
     * @return string
     */
    public function getStatus()
    {
        $status = 'running';
        if ($this->isFinished()) {
            $status = 'failed';
            if ($this->isSuccessful()) {
                $status = 'succeeded';
            }
        }
        return $status;
    }

    /**
     * Detects if the workflow has finished
     *
     * @return bool True if workflow has finished
     */
    public function isFinished()
    {
        $is_finished = (boolean)$this->get('result');
        return $is_finished;
    }

    /**
     * Detects if the workflow was successful
     *
     * @return bool True if workflow succeeded
     */
    public function isSuccessful()
    {
        $is_successful = ($this->get('result') == 'succeeded');
        return $is_successful;
    }

    /**
     * Returns a list of WorkflowOperations for this workflow
     *
     * @return WorkflowOperation[]
     */
    public function operations()
    {
        if (is_array($this->get('operations'))) {
            $operations_data = $this->get('operations');
        } else {
            $operations_data = [];
        }

        $operations = [];
        foreach ($operations_data as $operation_data) {
            $operations[] = $this->getContainer()->get(WorkflowOperation::class, [$operation_data]);
        }

        return $operations;
    }

    /**
     * Formats workflow object into an associative array for output
     *
     * @return array Associative array of data for output
     */
    public function serialize()
    {
        $user = 'Pantheon';
        if (isset($this->get('user')->email)) {
            $user = $this->get('user')->email;
        }
        if ($this->get('total_time')) {
            $elapsed_time = $this->get('total_time');
        } else {
            $elapsed_time = time() - $this->get('created_at');
        }

        $operations_data = [];
        foreach ($this->operations() as $operation) {
            $operations_data[] = $operation->serialize();
        }

        $data = [
            'id' => $this->id,
            'env' => $this->get('environment'),
            'workflow' => $this->get('description'),
            'user' => $user,
            'status' => $this->getStatus(),
            'time' => sprintf("%ds", $elapsed_time),
            'finished_at' => $this->get('finished_at'),
            'started_at' => $this->get('started_at'),
            'operations' => $operations_data,
        ];
        return $data;
    }

    /**
     * Waits on this workflow to finish
     *
     * @return Workflow|void
     * @throws TerminusException
     */
    public function wait()
    {
        while (!$this->isFinished()) {
            $this->fetch();
            sleep(3);
            /**
             * TODO: Output this to stdout so that it doesn't get mixed with any
             *   actual output. We can't use the logger here because that might be
             *   redirected to a log file where each line is timestamped.
             */
            fwrite(STDERR, '.');
        }
        echo "\n";
        if ($this->isSuccessful()) {
            return $this;
        } else {
            $final_task = $this->get('final_task');
            if (($final_task != null) && !empty($final_task->messages)) {
                foreach ($final_task->messages as $data => $message) {
                    if (!is_string($message->message)) {
                        $message->message = print_r($message->message, true);
                    }
                    throw new TerminusException((string)$message->message);
                }
            }
        }
    }

    /**
     * Check on the progress of a workflow. This can be called repeatedly and will apply a polling
     * period to prevent flooding the API with requests.
     *
     * @return bool Whether the workflow is finished or not
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function checkProgress()
    {
        // Fetch the workflow status from the API.
        $this->poll();

        if ($this->isFinished()) {
            // If the workflow failed then figure out the correct output message and throw an exception.
            if (!$this->isSuccessful()) {
                throw new TerminusException($this->getMessage());
            }
            return true;
        }

        return false;
    }

    /**
     * Get the success message of a workflow or throw an exception of the workflow failed.
     *
     * @return string The message to output to the user
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function getMessage()
    {
        if (!$this->isSuccessful()) {
            $message = 'Workflow failed.';
            $final_task = $this->get('final_task');
            if (!empty($final_task) && !empty($final_task->reason)) {
                $message = $final_task->reason;
            } elseif (!empty($final_task) && !empty($final_task->messages)) {
                foreach ($final_task->messages as $data => $message) {
                    if (!is_string($message->message)) {
                        $message = print_r($message->message, true);
                    } else {
                        $message = $message->message;
                    }
                }
            }
        } else {
            $message = $this->get('active_description');
        }

        return $message;
    }

    /**
     * Fetches this object from Pantheon. Waits a given length of time between checks.
     *
     * @return void
     */
    private function poll()
    {
        static $last_check = 0;

        // Poll for the workflow status. Don't check more often than the polling period
        $now = time();
        if ($last_check + Workflow::POLLING_PERIOD <= $now) {
            $this->fetch();
            $last_check = $now;
        }
    }
}
