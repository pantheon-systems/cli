<?php

namespace Pantheon\Terminus\Commands\Backup;

use Pantheon\Terminus\Commands\WorkflowProcessingTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;

/**
 * Class RestoreCommand
 * @package Pantheon\Terminus\Commands\Backup
 */
class RestoreCommand extends SingleBackupCommand
{
    use WorkflowProcessingTrait;

    /**
     * Restores a specific backup or the latest backup.
     *
     * @authorize
     *
     * @command backup:restore
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     * @option string $file [filename.tgz] Name of backup file on the plaform
     * @option string $element [all|code|files|database|db] Backup element
     * @throws TerminusException
     *
     * @usage <site>.<env> Restores the most recent backup of any type to <site>'s <env> environment.
     * @usage <site>.<env> --file=<backup> Restores backup with the <backup> file name to <site>'s <env> environment. Use terminus backup:list to identify available backups.
     * @usage <site>.<env> --element=<element> Restores the most recent <element> backup to <site>'s <env> environment.
     */
    public function restoreBackup($site_env, array $options = ['file' => null, 'element' => 'all',])
    {
        $this->validateElement($site_env, $options['element'], true);

        $site = $this->getSiteById($site_env);
        $env = $this->getEnv($site_env);

        if ($options['file'] && $options['element'] !== 'all') {
            throw new TerminusException('You cannot specify a file and an element at the same time.');
        }

        $elements = [];
        if ($options['element'] === 'all') {
            $elements = ['code', 'files', 'database'];
        } else {
            $elements[] = $this->getElement($options['element']);
        }

        $backups = $this->doGetBackups($site_env, $elements, $options);

        if (!$backups) {
            throw new TerminusNotFoundException(
                'No backups available. Create one with `terminus backup:create {site}.{env}`',
                [
                    'site' => $this->getSiteById($site_env)->getName(),
                    'env' => $env->getName(),
                ]
            );
        }

        if (
            !$this->confirm(
                'Are you sure you want to restore to {env} on {site}?',
                ['site' => $site->getName(), 'env' => $env->getName()]
            )
        ) {
            return;
        }

        $this->doRestoreBackups($backups, $env->id);
    }

    /**
     * Get backups for given elements.
     *
     * @param string $site_env
     *   Site & environment in the format `site-name.env`
     * @param array $elements
     *   Elements to get backups for.
     * @param array $options
     *   Options to pass to the getBackup function.
     */
    protected function doGetBackups(string $site_env, array $elements, array $options)
    {
        $backups = [];
        foreach ($elements as $element) {
            $options['element'] = $element;
            try {
                $backup = $this->getBackup($site_env, $options);
                if ($backup->get('type') === $element) {
                    $backups[$element] = $backup;
                }
            } catch (TerminusNotFoundException $e) {
                continue;
            }
        }
        return $backups;
    }

    /**
     * Restore backups to given site.
     *
     * @param array $backups
     *   Backup files to restore
     * @param string $env
     *   Environment name.
     */
    protected function doRestoreBackups(array $backups, string $env)
    {
        foreach ($backups as $type => $backup) {
            $workflow = $backup->restore();
            try {
                $this->processWorkflow($workflow);
                $this->log()->notice('Restored the {type} backup to {env}.', ['env' => $env, 'type' => $type]);
            } catch (\Exception $e) {
                $message = $workflow->getMessage();
                if (trim($message) == 'Successfully queued restore_site') {
                    $message = 'There was an error while restoring your backup.';
                }
                throw new TerminusException($message);
            }
        }
    }
}
