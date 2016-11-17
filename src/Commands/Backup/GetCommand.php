<?php

namespace Pantheon\Terminus\Commands\Backup;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;

class GetCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * Fetch the download URL for a specific backup or latest backup
     *
     * @authorized
     *
     * @command backup:get
     *
     * @param string $site_env Site & environment to deploy to, in the form `site-name.env`.
     * @option string $file [filename.tgz] Name of the backup archive file
     * @option string $element [code|files|database|db] Backup type
     * @throws TerminusNotFoundException
     *
     * @usage terminus backup:get awesome-site.dev
     *     Returns the URL for the most recent backup of any type
     * @usage terminus backup:get awesome-site.dev --file=2016-08-18T23-16-20_UTC_code.tar.gz
     *     Returns the URL for the backup with the specified archive file name
     * @usage terminus backup:get awesome-site.dev --element=code
     *     Returns the URL for the most recent code backup
     */
    public function getBackup($site_env, array $options = ['file' => null, 'element' => null,])
    {
        list($site, $env) = $this->getSiteEnv($site_env);

        if (isset($options['file']) && !is_null($file_name = $options['file'])) {
            $backup = $env->backups->getBackupByFileName($file_name);
        } else {
            $element = ($options['element'] == 'db') ? 'database' : $options['element'];
            $backups = $env->backups->getFinishedBackups($element);
            if (empty($backups)) {
                throw new TerminusNotFoundException(
                    'No backups available. Create one with `terminus backup:create {site}.{env}`',
                    ['site' => $site->get('name'), 'env' => $env->id,]
                );
            }
            $backup = array_shift($backups);
        }

        return $backup->getUrl();
    }
}
