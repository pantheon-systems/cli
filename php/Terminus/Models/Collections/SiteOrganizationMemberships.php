<?php

namespace Terminus\Models\Collections;

class SiteOrganizationMemberships extends NewCollection {
  /**
   * @var Site
   */
  public $site;
  /**
   * @var bool
   */
  protected $paged = true;
  /**
   * @var string
   */
  protected $collected_class = 'Terminus\Models\SiteOrganizationMembership';

  /**
   * Instantiates the collection
   *
   * @param array $options To be set
   * @return SiteOrganizationMemberships
   */
  public function __construct(array $options = []) {
    parent::__construct($options);
    $this->site = $options['site'];
    $this->url  = "sites/{$this->site->id}/memberships/organizations";
  }

  /**
   * Adds this org as a member to the site
   *
   * @param string $name Name of site to add org to
   * @param string $role Role for supporting organization to take
   * @return Workflow
   **/
  public function create($name, $role) {
    $workflow = $this->site->workflows->create(
      'add_site_organization_membership',
      ['params' => ['organization_name' => $name, 'role' => $role,],]
    );
    return $workflow;
  }

  /**
   * Retrieves the model of the given ID or name
   *
   * @param string $id ID or name of desired model instance
   * @return SiteOrganizationMembership
   */
  public function get($id) {
    $org_member = parent::get($id);
    if (!is_null($org_member)) {
      return $org_member;
    }
    foreach ($this->models as $org_member) {
      if ($name == $org_member->get('profile')->name) {
        return $org_member;
      }
    }
    return null;
  }

}
