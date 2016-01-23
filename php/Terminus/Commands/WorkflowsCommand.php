<?php

namespace Terminus\Commands;

use Terminus;
use Terminus\Auth;
use Terminus\Utils;
use Terminus\Commands\TerminusCommand;
use Terminus\Exceptions\TerminusException;
use Terminus\Helpers\Input;
use Terminus\Models\User;
use Terminus\Models\Collections\Sites;

define("WORKFLOWS_WATCH_INTERVAL", 5);

/**
* Actions to be taken on an individual site
*/
class WorkflowsCommand extends TerminusCommand {
  protected $_headers = false;

  /**
   * Object constructor.
   */
  public function __construct() {
    Auth::ensureLogin();
    parent::__construct();
    $this->sites = new Sites();
  }

  /**
   * List Workflows for a Site
   *
   * ## OPTIONS
   * [--site=<site>]
   * : Site from which to list workflows
   *
   * @subcommand list
   */
  public function index($args, $assoc_args) {
    $site = $this->sites->get(
      Input::siteName(array('args' => $assoc_args))
    );
    $site->workflows->fetch(array('paged' => false));
    $workflows = $site->workflows->all();

    $data = array();
    foreach ($workflows as $workflow) {
      $workflow_data = $workflow->serialize();
      unset($workflow_data['operations']);
      $data[] = $workflow_data;
    }
    if (count($data) == 0) {
      $this->log()->warning(
        'No workflows have been run on {site}.',
        array('site' => $site->get('name'))
      );
    }
    $this->output()->outputRecordList($data);
  }

  /**
   * Show operation details for a workflow
   *
   * ## OPTIONS
   * [--workflow_id]
   * : Uuid of workflow to show
   * [--site=<site>]
   * : Site from which to list workflows
   * [--latest-with-logs]
   * : Display the most-recent workflow with logs
   *
   * @subcommand show
   */
  public function show($args, $assoc_args) {
    $site = $this->sites->get(Input::siteName(array('args' => $assoc_args)));

    if (isset($assoc_args['workflow_id'])) {
      $workflow_id = $assoc_args['workflow_id'];
      $model_data = (object)array('id' => $workflow_id);
      $workflow = $site->workflows->add($model_data);
    } elseif (isset($assoc_args['latest-with-logs'])) {
      $site->workflows->fetch(array('paged' => false));
      $workflow = $site->workflows->findLatestWithLogs();
      if (!$workflow) {
        $this->log()->info('No recent workflow has logs');
        return;
      }
    } else {
      $site->workflows->fetch(array('paged' => false));
      $workflows = $site->workflows->all();
      $workflow = Input::workflow(compact('workflows'));
    }
    $workflow->fetchWithLogs();

    $workflow_data = $workflow->serialize();
    if (Terminus::getConfig('format') == 'normal') {
      unset($workflow_data['operations']);
      $this->output()->outputRecord($workflow_data);

      $operations = $workflow->operations();
      if (count($operations)) {
        // First output a table of operations without logs
        $operations_data = array_map(
          function($operation) {
            $operation_data = $operation->serialize();
            unset($operation_data['id']);
            unset($operation_data['log_output']);
            return $operation_data;
          },
          $operations
        );

        $this->output()->outputRecordList(
          $operations_data,
          array('description' => 'Operation Description')
        );

        // Second output the logs
        foreach ($operations as $operation) {
          if ($operation->has('log_output')) {
            $log_msg = sprintf(
              "\n------ %s ------\n%s",
              $operation->description(),
              $operation->get('log_output')
            );
            $this->output()->outputValue($log_msg);
          }
        }
      } else {
        $this->output()->outputValue('Workflow has no operations');
      }
    } else {
      $this->output()->outputRecord($workflow_data);
    }
  }

  /**
   * Streams new and finished workflows to the console
   *
   * ## OPTIONS
   * [--site=<site>]
   * : Site from which to list workflows
   *
   * @subcommand watch
   */
  public function watch($args, $assoc_args) {
    $site = $this->sites->get(Input::siteName(array('args' => $assoc_args)));

    // Keep track of workflows that have been printed.
    // This is necessary because the local clock may drift from
    // the server's clock, causing events to be printed twice.
    $started = array();
    $finished = array();

    $this->logger->info('Watching workflows...');
    $site->workflows->fetchWithOperations();
    while (true) {
      $last_created_at = $site->workflows->lastCreatedAt();
      $last_finished_at = $site->workflows->lastFinishedAt();
      sleep(WORKFLOWS_WATCH_INTERVAL);
      $site->workflows->fetchWithOperations();

      $workflows = $site->workflows->all();
      foreach ($workflows as $workflow) {
        if (($workflow->get('created_at') > $last_created_at)
          && !in_array($workflow->id, $started)
        ) {
          array_push($started, $workflow->id);

          $started_message = sprintf(
            "Started %s %s (%s)",
            $workflow->id,
            $workflow->get('description'),
            $workflow->get('environment')
          );
          $this->logger->info($started_message);
        }

        if (($workflow->get('finished_at') > $last_finished_at)
          && !in_array($workflow->id, $finished)
        ) {
          array_push($finished, $workflow->id);

          $finished_message = sprintf(
            "Finished Workflow %s %s (%s)",
            $workflow->id,
            $workflow->get('description'),
            $workflow->get('environment')
          );
          $this->logger->info($finished_message);

          if ($workflow->get('has_operation_log_output')) {
            $workflow->fetchWithLogs();
            $operations = $workflow->operations();
            foreach ($operations as $operation) {
              if ($operation->has('log_output')) {
                $log_msg = sprintf(
                  "\n------ %s (%s) ------\n%s",
                  $operation->description(),
                  $operation->get('environment'),
                  $operation->get('log_output')
                );
                $this->log()->info($log_msg);
              }
            }
          }
        }
      }
    }
  }

}

Terminus::addCommand('workflows', 'WorkflowsCommand');
