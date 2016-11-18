<?php

namespace Pantheon\Terminus\Models;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Terminus\Collections\Tags;

class OrganizationSiteMembership extends TerminusModel implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var Organization
     */
    public $organization;
    /**
     * @var Site
     */
    public $site;
    /**
     * @var Tags
     */
    public $tags;

    /**
     * @inheritdoc
     */
    public function __construct($attributes = null, array $options = [])
    {
        parent::__construct($attributes, $options);
        $this->organization = $options['collection']->organization;
        // @TODO: Convert Site and use the container to instantiate.
        $this->site = new Site($attributes->site);
        $this->site->memberships = [$this,];
        $this->site->tags = new Tags(
            ['data' => (array)$attributes->tags, 'org_site_membership' => $this,]
        );
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return "{$this->organization->id}: {$this->organization->get('profile')->name}";
    }

    /**
     * Removes a site from this organization
     *
     * @return Workflow
     */
    public function delete()
    {
        $workflow = $this->getOrganization()->getWorkflows()->create(
            'remove_organization_site_membership',
            ['params' => ['site_id' => $this->site->id,],]
        );
        return $workflow;
    }

    /**
     * @return Organization
     */
    public function getOrganization()
    {
        if (empty($this->organization)) {
            $this->organization = $this->getContainer()->get(Organization::class, [$this->get('organization')]);
            $this->organization->memberships = [$this,];
        }
        return $this->organization;
    }
}
