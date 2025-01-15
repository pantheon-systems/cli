<?php

namespace Pantheon\Terminus\Collections;

use Exception;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;
use Pantheon\Terminus\Models\Site;
use Pantheon\Terminus\Models\SiteOrganizationMembership;
use Pantheon\Terminus\Models\TerminusModel;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\Session\SessionAwareInterface;
use Pantheon\Terminus\Session\SessionAwareTrait;

/**
 * Class Sites.
 *
 * @package Pantheon\Terminus\Collections
 */
class Sites extends APICollection implements SessionAwareInterface
{
    use SessionAwareTrait;

    public const PRETTY_NAME = 'sites';

    /**
     * @var string
     */
    protected $collected_class = Site::class;

    /**
     * @var array
     */
    protected $site_names = [];

    /**
     * Creates a new site.
     *
     * @param string[] $params
     *   Options for the new site, elements as follows:
     *     string label The site's human-friendly name
     *     string site_name The site's name
     *     string organization_id Organization to which this site belongs' UUID
     *
     * @return \Pantheon\Terminus\Models\Workflow
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function create($params = []): \Pantheon\Terminus\Models\Workflow
    {
        return $this->getUser()->getWorkflows()->create('create_site', compact('params'));
    }

    /**
     * Creates a new site for migration.
     *
     * @param string[] $params
     *   Options for the new site, elements as follows:
     *     string label The site's human-friendly name
     *     string site_name The site's name
     *     string organization_id Organization to which this site belongs' UUID
     *     string type Workflow type for imports
     *
     * @return \Pantheon\Terminus\Models\Workflow
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function createForMigration($params = []): Workflow
    {
        return $this->getUser()->getWorkflows()->create('create_site_for_migration', compact('params'));
    }

    /**
     * Fetches model data from API and instantiates its model instances.
     *
     * @param array $options
     *   Options to change the requests made. Elements as follows:
     *     string  org_id    UUID of the organization to retrieve sites for
     *     boolean team_only True to only retrieve team sites
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function fetch(array $options = []): Sites
    {
        $defaultOptions = [
            'org_id' => null,
            'team_only' => false,
        ];
        $options = array_merge($defaultOptions, $options);
        $sites = &$this->models;
        $sites = null === $options['org_id'] ? $this->getUser()->getSites() : [];

        if ($options['team_only']) {
            return $this;
        }

        $orgMemberships = array_filter(
            $this->getUser()->getOrganizationMemberships()->fetch()->all(),
            fn ($orgMembership) => (null === $options['org_id'] || $orgMembership->id === $options['org_id'])
                && $orgMembership->get('role') !== SiteOrganizationMembership::ROLE_UNPRIVILEGED
        );

        /** @var \Pantheon\Terminus\Models\SiteOrganizationMembership $orgMembership */
        foreach ($orgMemberships as $orgMembership) {
            foreach ($orgMembership->getOrganization()->getSites() as $id => $site) {
                if (!isset($sites[$id])) {
                    $sites[$id] = $site;
                    continue;
                }

                $sites[$id]->memberships[] = $site->memberships[0];
                if (!isset($sites[$id]->tags)) {
                    $sites[$id]->tags = $site->tags;
                    continue;
                }

                $sites[$id]->tags->models = array_merge(
                    $sites[$id]->tags->models ?? [],
                    $site->tags->models ?? [],
                );
            }
        }

        return $this;
    }

    /**
     * Filters the members of this collection by their names.
     *
     * @param string $regex
     *   Non-delimited PHP regex to filter site names by
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function filterByName($regex = '(.*)')
    {
        return $this->filterByRegex('name', $regex);
    }

    /**
    * Filters the members of this collection by their label.
    *
    * @param string $regex
    *   Non-delimited PHP regex to filter site names by
    *
    * @return Sites
    */
    public function filterByLabel($regex = '(.*)')
    {
        return $this->filterByRegex('label', $regex);
    }

    /**
     * Filters an array of sites by the plan name.
     *
     * @param string $plan_name
     *   Name of the plan to filter by.
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function filterByPlanName($plan_name)
    {
        $plan_name = strtolower($plan_name ?? '');
        return $this->filter(function ($model) use ($plan_name) {
            return strtolower($model->get('plan_name') ?? '') === $plan_name;
        });
    }

    /**
     * Filters an array of sites by whether the user is an organizational member.
     *
     * @param string $owner_uuid
     *   UUID of the owning user to filter by.
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function filterByOwner($owner_uuid)
    {
        return $this->filter(function ($model) use ($owner_uuid) {
            return ($model->get('owner') == $owner_uuid);
        });
    }

    /**
     * Filters sites list by tags separated by a comma (ANY).
     *
     * @param string $tag
     *   Comma-separated list of tags to filter by.
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function filterByTag($tag)
    {
        return $this->filter(function ($site) use ($tag) {
            return (empty($tag)) ? $site->tags->containsNone() : $site->tags->containsAny($this->splitString($tag));
        });
    }

    /**
     * Filters sites list by tags separated by a comma (ALL).
     *
     * @param string $tags Comma-separated list of tags to filter by
     * @return Sites
     */
    public function filterByTags($tags)
    {
        return $this->filter(function ($site) use ($tags) {
            return (empty($tags)) ? $site->tags->containsNone() : $site->tags->containsAll($this->splitString($tags));
        });
    }

    /**
     * Filters sites list by upstream.
     *
     * @param string $upstream_id
     *   An upstream to filter by.
     *
     * @return \Pantheon\Terminus\Collections\Sites
     */
    public function filterByUpstream($upstream_id)
    {
        $upstream_id = strtolower($upstream_id ?? '');
        return $this->filter(function ($model) use ($upstream_id) {
            return in_array($upstream_id, $model->getUpstream()->getReferences());
        });
    }

    /**
     * Retrieves the site of the given UUID or name.
     *
     * If the site list has already been fetched then this function will search for the site in the fetched list.
     * If no sites have been fetched yet then it will query the API. Use caution when calling this function after
     * a manual fetch as it may be just searching a subset of the user's sites.
     *
     * @param string $id
     *   UUID or name of desired site.
     *
     * @return \Pantheon\Terminus\Models\Site
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function get($id): TerminusModel
    {
        try {
            $uuid = $this->getUuid($id);
            if (isset($this->models[$uuid])) {
                return $this->models[$uuid];
            }

            $nickname = 'site-' . $uuid;
            $this->getContainer()->add($nickname, $this->collected_class)
                ->addArguments(
                    [
                        (object)['id' => $uuid],
                        ['id' => $uuid, 'collection' => $this],
                    ]
                );
            $site = $this->getContainer()->get($nickname);
            $site->fetch();
            $this->models[$uuid] = $site;

            return $this->models[$uuid];
        } catch (Exception $e) {
            throw new TerminusException(
                'Could not locate a site your user may access identified by {id}: {error_message}',
                ['id' => $id, 'error_message' => $e->getMessage()],
            );
        }
    }

    /**
     * Determines whether a given site name is taken or not.
     *
     * @param string $name
     *   Name of the site to look up.
     *
     * @return boolean
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function nameIsTaken(string $name): bool
    {
        try {
            $this->getUuidByName($name);

            return true;
        } catch (TerminusNotFoundException $e) {
            return false;
        }
    }

    /**
     * Looks up a site's UUID by its name.
     *
     * @param string $name
     *   Name of the site to look up.
     *
     * @return string
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Pantheon\Terminus\Exceptions\TerminusNotFoundException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUuidByName(string $name): string
    {
        if (isset($this->site_names[$name])) {
            return $this->site_names[$name];
        }

        $response = $this->request()->request(
            'site-names/' . $name,
            ['method' => 'get',]
        );

        if ($response->isError() || !isset($response->getData()->id)) {
            throw new TerminusNotFoundException($response->getData());
        }

        $id = $response->getData()->id;
        $this->site_names[$name] = $id;

        return $id;
    }

    /**
     * Returns the site UUID.
     *
     * @param string $site_id
     *   The site name or UUID.
     * @return string|null
     *   The site UUID.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Pantheon\Terminus\Exceptions\TerminusNotFoundException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUuid(string $site_id): ?string
    {
        if (!$this->isValidUuid($site_id)) {
            return $this->getUuidByName($site_id);
        }

        $response = $this->request()->request(sprintf('sites/%s', $site_id), ['method' => 'get']);
        if ($response->isError()) {
            throw new TerminusNotFoundException($response->getData());
        }

        return $site_id;
    }

    /**
     * Returns TRUE if the string is a valid UUID value.
     *
     * @param string $uuid
     *
     * @return bool
     */
    protected function isValidUuid(string $uuid): bool
    {
        return preg_match('/[a-f0-9]{8}-([a-f0-9]{4}-){3}[a-f0-9]{12}/', strtolower($uuid));
    }
}
