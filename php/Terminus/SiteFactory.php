<?php
namespace Terminus;
use Terminus\Site;
use Terminus\Session;
use \Terminus_Command;

class SiteFactory {
  private static $instance = null;
  private $sites = array();

  public function __construct() {
    $this->hydrate();
    return $this;
  }

  private function hydrate() {
    $request = Terminus_Command::request( 'users', Session::getValue('user_uuid'), 'sites', 'GET', Array('hydrated' => true) );
    $sites = $request['data'];
    foreach ($sites as $site_id => $site_data) {
      // we need to skip sites that are in the build process still
      if (!isset($site_data->information)) continue;
      $site_data->id = $site_id;
      $this->sites[$site_data->information->name] = new Site($site_data);
    }

    return $this;
  }

  public static function instance($sitename = null) {
    if (!self::$instance) {
      self::$instance = new self();
    }

    $factory = self::$instance;

    if ($sitename) {
      return $factory->getSite($sitename);
    } else {
      return $factory->getAll();
    }
  }

  public function getSite($sitename) {
    if (!array_key_exists($sitename,$this->sites)) {
      throw new \Exception(sprintf('No site exists named "%s"', $sitename));
    }
    if (isset($this->sites[$sitename])) {
      // if we haven't instatiated yet, do that now
      if ("Terminus\Site" != get_class($this->sites[$sitename])) {
        $this->sites[$sitename] = new Site($this->sites[$sitename]);
      }
      return $this->sites[$sitename];
    }
    return false;
  }

  /**
   * Helper for getting a site by UUID.
   */
  public function getSiteByUUID($site_uuid) {
    foreach ($this->sites as $sitename => $site) {
      if ($site->id == $site_uuid) {
        break;
      }
      else {
        $site = FALSE;
      }
    }
    if (!$site) {
      throw new \Exception(sprintf('No site exists with the UUID "%s"', $site_uuid));
    }
    if (isset($this->sites[$sitename])) {
      // if we haven't instatiated yet, do that now
      if ("Terminus\Site" != get_class($this->sites[$sitename])) {
        $this->sites[$sitename] = new Site($this->sites[$sitename]);
      }
      return $this->sites[$sitename];
    }
  }

  public function getAll() {
    return $this->sites;
  }
}
