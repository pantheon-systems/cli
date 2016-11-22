<?php

namespace Pantheon\Terminus\Models;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Pantheon\Terminus\Collections\Tags;

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
     * @var \stdClass
     */
    protected $site_data;
    /**
     * @var \stdClass
     */
    protected $tags_data;


    /**
     * @inheritdoc
     */
    public function __construct($attributes = null, array $options = [])
    {
        parent::__construct($attributes, $options);
        $this->organization = $options['collection']->organization;
        $this->site_data = $attributes->site;
        $this->tags_data = $attributes->tags;
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
        $workflow = $this->organization->getWorkflows()->create(
            'remove_organization_site_membership',
            ['params' => ['site_id' => $this->getSite()->get('id'),],]
        );
        return $workflow;
    }

    /**
     * @return Site
     */
    public function getSite()
    {
        if (!$this->site) {
            $this->site = $this->getContainer()->get(Site::class, [$this->site_data]);
            $this->site->memberships = [$this,];
            $this->site->tags = $this->getContainer()->get(
                Tags::class,
                [
                    ['data' => (array)$this->tags_data, 'org_site_membership' => $this,]
                ]
            );
        }
        return $this->site;
    }
}
