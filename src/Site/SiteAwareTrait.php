<?php

namespace Pantheon\Terminus\Site;

use Terminus\Collections\Sites;
use Terminus\Exceptions\TerminusException;

/**
 * Implements the SiteAwareInterface for dependency injection of the Sites collection.
 *
 * Class SiteAwareTrait
 * @package Pantheon\Terminus\Site
 */
trait SiteAwareTrait
{
    /**
     * @var Sites
     */
    protected $sites;

    /***
     * @param Sites $sites
     * @return void
     */
    public function setSites(Sites $sites)
    {
        $this->sites = $sites;
    }

    /**
     * @return Sites The sites collection for the authenticated user.
     */
    public function sites()
    {
        return $this->session()->getUser()->sitesCollection();
    }

    /**
     * Look up a site by id.
     *
     * @param Site $site_id Either a site's UUID or its name
     * @return mixed
     */
    public function getSite($site_id)
    {
        return $this->sites()->get($site_id);
    }

    /**
     * Get the site and environment with the given ids.
     *
     * @TODO This should be moved to the input/validation stage when that is available.
     *
     * @param string $site_env_id The site/environment id in the form <site>[.<env>]
     * @param string $default_env The default environment to use if none is specified.
     * @return array The site and environment in an array.
     * @throws \Terminus\Exceptions\TerminusException
     */
    public function getSiteEnv($site_env_id, $default_env = null)
    {
        $parts = explode('.', $site_env_id);
        $site_id = $parts[0];
        $env_id = !empty($parts[1]) ? $parts[1] : $default_env;

        if (empty($site_id) || empty($env_id)) {
            throw new TerminusException('The environment argument must be given as <site_name>.<environment>');
        }

        $site = $this->getSite($site_id);
        $env = $site->environments->get($env_id);
        return [$site, $env];
    }
}
