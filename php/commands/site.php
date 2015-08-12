<?php
/**
 * actions on an individual site
 *
 */

use Terminus\Utils;
use Terminus\Auth;
use Terminus\SiteFactory;
use Terminus\Site;
use Terminus\User;
use Terminus\Collections\Instruments;
use \Guzzle\Http\Client;
use \Terminus\Loggers\Regular as Logger;
use \Terminus\Helpers\Input;
use \Terminus\Deploy;
use Terminus\SitesCache;


class Site_Command extends Terminus_Command {
  public $sitesCache;

  public function __construct() {
    parent::__construct();

    $this->sitesCache = new SitesCache();
  }

  protected $_headers = false;

  /**
  * Get or set site attributes
  *
  * ## OPTIONS
  *
  * [--site=<site>]
  * : site to check attributes on
  *
  * [--env=<env>]
  * : environment
  *
  * ## EXAMPLES
  *
  **/
  public function attributes($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $data = $site->attributes();
    $this->handleDisplay($data, array(), array('Attribute','Value'));
  }

  /**
   * Create a branch for developing
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : site to create branch of
   *
   * --branch=<branch>
   * : name of new branch
   *
   * ## EXAMPLES
   *
   * terminus branch-create --site=yoursite --branch=carebearsandunicorns
   *
   * @subcommand branch-create
  **/
  public function branch_create($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $branch = preg_replace('#[_\s]+#',"",@$assoc_args['branch']);
    $branch = $site->createBranch($branch);
  }

  /**
   * Clear all site caches
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : site to use
   *
   * [--env=<env>]
   * : Environment to clear
   *
   * ## EXAMPLES
   *  terminus site clear-caches --site=test
   *
   * @subcommand clear-caches
   */
  public function clear_caches($args, $assoc_args) {
      $site = SiteFactory::instance(Input::sitename($assoc_args));
      $env_id = Input::env($assoc_args, 'env');
      $workflow = $site->workflows->create('clear_cache', array('environment' => $env_id));
      $workflow->wait();
      Terminus::success("Caches cleared");
  }

  /**
   * Code related commands
   *
   * ## OPTIONS
   *
   * <log|branches|branch-create|diffstat|commit>
   * : options are log, branches, branch-create, diffstat, commit
   *
   * [--site=<site>]
   * : name of the site
   *
   * [--env=<env>]
   * : site environment
   *
   * [--message=<message>]
   * : message to use when committing on server changes
   *
   * [--branchname=<branchname>]
   * : When using branch-create specify the branchname
   */
  public function code($args, $assoc_args) {
      $subcommand = array_shift($args);
      $site = SiteFactory::instance(Input::sitename($assoc_args));
      $data = $headers = array();
      switch($subcommand) {
        case 'log':
          $env = Input::env($assoc_args, 'env');
          $logs = $site->environment($env)->log();
          $data = array();
          foreach ($logs as $log) {
            $data[] = array(
              'time' => $log->datetime,
              'author' => $log->author,
              'labels' => join(", ", $log->labels),
              'hash'  => $log->hash,
              'message' => trim(str_replace("\n",'',str_replace("\t",'',substr($log->message,0,50)))),
            );
          }
          break;
        case 'branches':
          $data = $site->tips();
          $headers = array('Branch','Commit');
          break;
        case 'branch-create':
          if (!isset($assoc_args['branchname'])) {
            $branch = Terminus::prompt("Name of new branch");
          } else {
            $branch = $assoc_args['branchname'];
          }
          $branch = preg_replace('#[_\s]+#',"",@$assoc_args['branchname']);
          $branch = $site->createBranch($branch);
          Terminus::success('Branch created');
          break;
        case 'commit':
          $env = Input::env($assoc_args, 'env');
          $diff = $site->environment($env)->diffstat();
          $count = count($diff);
          if (!Terminus::get_config('yes')) {
            Terminus::confirm("Commit %s changes?", $assoc_args, array($count));
          }
          $message = @$assoc_args['message'] ?: "Terminus commit.";
          $data = $site->environment($env)->onServerDev(null, $message);
          Terminus::success("Successfully committed.");
          return true;
          break;
        case 'diffstat':
          $env = Input::env($assoc_args, 'env');
          $diff = (array) $site->environment($env)->diffstat();
          if (empty($diff)) {
            Terminus::success("No changes on server.");
            return true;
          }
          $data = array();
          // munge the data
          $filter = @$assoc_args['filter'] ?: false;
          foreach ($diff as $file => $stats) {
            if ($filter) {
              $filter = preg_quote($filter,'/');
              $regex = '/'.$filter.'/';
              if (!preg_match($regex, $file)) {
                continue;
              }
            }
            $data[] = array_merge( array('file'=>$file), (array) $stats );
          }
          break;
      }

      if(!empty($data)) {
        $this->handleDisplay($data, array(), $headers);
      }
      return $data;
  }

  /**
  * Connection related commands
  *
  * ## OPTIONS
  *
  * [--site=<site>]
  * : name of the site
  *
  * [--env=<env>]
  * : site environment
  *
  * [--set=<value>]
  * : set connection to sftp or git
  *
  * @subcommand connection-mode
  */
  public function connection_mode($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $action = 'show';
    $mode = @$assoc_args['set'] ?: false;
    if (@$assoc_args['set']) {
      $action = 'set';
    }

    # Only present dev and multidev environments; Test/Live cannot be modified
    $site->environmentsCollection->fetch();
    $environments = array_diff($site->environmentsCollection->ids(), array('test', 'live'));

    $env = Input::env($assoc_args, 'env', 'Choose environment', $environments);
    if (($env == 'test' || $env == 'live') && $action == 'set') {
      Terminus::error("Connection mode cannot be set in Test or Live environments");
    }
    $data = $headers = array();
    switch($action) {
      case 'show':
        $data = $site->environment($env)->onServerDev();
        $mode = (isset($data->enabled) && (int)$data->enabled===1) ? 'Sftp' : 'Git';
        Logger::coloredOutput("%YConnection mode:%n $mode");
        return;
        break;
      case 'set':
        if (!$mode) {
          Terminus::error("You must specify the mode with --set=<sftp|git>");
        }
        $data = $site->environment($env)->onServerDev($mode);
        Terminus::success("Successfully changed connection mode to $mode");
        break;
    }
    return $data;
  }

  /**
   * Open the Pantheon site dashboard in a browser
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : site dashboard to open
   *
   * [--env=<env>]
   * : site environment to display in the dashboard
   *
   * [--print]
   * : don't try to open the link, just print it
   *
   * @subcommand dashboard
  */
  public function dashboard($args, $assoc_args) {
    switch ( php_uname('s') ) {
      case "Linux":
        $cmd = "xdg-open"; break;
      case "Darwin":
        $cmd = "open"; break;
      case "Windows NT":
        $cmd = "start"; break;
    }
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $env = Input::optional( 'env', $assoc_args );
    $env = $env ? sprintf( "#%s", $env ) : null;
    $url = sprintf("https://dashboard.pantheon.io/sites/%s%s", $site->getId(), $env);
    if ( isset($assoc_args['print']) ) {
      Logger::coloredOutput("%GDashboard URL:%n " . $url);
    }
    else {
      Terminus::confirm("Do you want to open your dashboard link in a web browser?", Terminus::get_config());
      $command = sprintf("%s %s", $cmd, $url);
      exec($command);
    }
  }

  /**
   * Retrieve information about the site
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : name of the site to work with
   *
   * [--field=<field>]
   * : field to return
   *
   * ## EXAMPLES
   *
   */
  public function info($args, $assoc_args) {
    $sitename = Input::sitename($assoc_args);
    $site_id = $this->sitesCache->findID($sitename);
    $site = new Site($site_id);

    $site->fetch();

    # Fetch environment data for sftp/git connection info
    $site->environmentsCollection->fetch();

    if (isset($assoc_args['field'])) {
      $field = $assoc_args['field'];
      Terminus::line($site->info($field));
    } else {
      $this->handleDisplay($site->info(), $args);
    }
  }

  /**
   * List site organizations
   *
   * ## OPTIONS
   *
   * <list|add|remove>
   * : subfunction to run
   *
   * [--site=<site>]
   * : Site's name
   *
   * [--org=<org_name|org_id>]
   * : When adding: by name; when removing: by id
   *
   * [--role=<role>]
   * : Max role for organization on this site ... default "team_member"
   *
   */
  public function organizations($args, $assoc_args) {
    $action = array_shift($args);
    $site = SiteFactory::instance( Input::sitename($assoc_args) );
    $data = array();
    switch ($action) {
        case 'add':
          $role = Input::optional('role', $assoc_args, 'team_member');
          $org = Input::orgname($assoc_args,'org');
          $workflow = $site->addMembership('organization',$org, $role);
          $workflow->wait();
          Terminus::success("Organization successfully added");
          $orgs = $site->memberships();
          break;
        case 'remove':
          $org = Input::orgid($assoc_args, 'org');
          $workflow = $site->removeMembership('organization',$org);
          $workflow->wait();
          Terminus::success("Organization successfully removed");
          $orgs = $site->memberships();
          break;
        case 'default':
        case 'list':
          $orgs = $site->memberships();
          break;
    }
    if (empty($orgs)) {
      Terminus::error("No organizations");
    }

    // format the data
    foreach ($orgs as $org) {
      $data[] = array(
        'label' => "{$org->organization->profile->name}",
        'name'  => $org->organization->profile->machine_name,
        'role'  => $org->role,
        'id' => $org->organization_id,
      );
    }

    $this->handleDisplay($data);
  }

 /**
  * Get, load, create, or list backup information
  *
  * ## OPTIONS
  *
  * <get|load|create|list>
  * : Function to run - get, load, create, or list
  *
  * [--site=<site>]
  * : Site to load
  *
  * [--env=<env>]
  * : Environment to load
  *
  * [--element=<code|files|db|all>]
  * : Element to download or create. *all* only used for 'create'
  *
  * [--to-directory=<directory>]
  * : Absolute path of directory to download the file
  *
  * [--latest]
  * : If set the latest backup will be selected automatically
  *
  * [--keep-for]
  * : Number of days to keep this backup
  *
  * @subcommand backup
  *
  */
   public function backup($args, $assoc_args) {
     $action = array_shift($args);
     $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
     $env = Input::env($assoc_args, 'env');
     switch ($action) {
       case 'get':
         //Backward compatability supports "database" as a valid element value.
         if(@$assoc_args['element'] == 'database') {
           $assoc_args['element'] = 'db';
         }

         // prompt for backup type
         if (!$element = @$assoc_args['element']) {
           $element = Terminus::menu(array('code','files','db'), null, "Select type backup", TRUE);
         }

         if (!in_array($element,array('code','files','db'))) {
           Terminus::error("Invalid backup element specified.");
         }
         $latest = Input::optional('latest',$assoc_args,false);
         $backups = $site->environment($env)->backups($element, $latest);
         if (empty($backups)) {
           \Terminus::error('No backups available.');
         }
         $menu = $folders = array();

         // build a menu for selecting back ups
         foreach( $backups as $folder => $backup ) {
           if (!isset($backup->filename)) continue;
           if (!isset($backup->folder)) $backup->folder = $folder;
           $buckets[] = $backup->folder;
           $menu[] = $backup->filename;
         }

         if (empty($menu)) {
           Terminus::error("No backups available. Create one with `terminus site backup create --site=%s --env=%s`", array($site->getName(),$env));
         }

         $index = 0;
         if (!$latest) {
           $index = Terminus::menu($menu, null, "Select backup");
         }
         $bucket = $buckets[$index];
         $filename = $menu[$index];

         $url = $site->environment($env)->backupUrl($bucket,$element);

         if (isset($assoc_args['to-directory'])) {
           Terminus::line("Downloading ... please wait ...");
           $filename = \Terminus\Utils\get_filename_from_url($url->url);
           $target = sprintf("%s/%s", $assoc_args['to-directory'], $filename);
           if (Terminus_Command::download($url->url, $target)) {
             Terminus::success("Downloaded %s", $target);
             return $target;
           } else {
             Terminus::error("Could not download file");
           }
         }
         echo $url->url;
         return $url->url;
         break;
      case 'load':
        $assoc_args['to-directory'] = '/tmp';
        $assoc_args['element'] = 'database';
        $database = @$assoc_args['database'] ?: false;
        $username = @$assoc_args['username'] ?: false;
        $password = @$assoc_args['password'] ?: false;

        exec("mysql -e 'show databases'",$stdout, $exit);
        if ( 0 != $exit ) {
          Terminus::error("MySQL does not appear to be installed on your server.");
        }

        $assoc_args['env'] = $env;
        $target = $this->backup(array('get'), $assoc_args);
        $target = \Terminus\Utils\get_filename_from_url($target);
        $target = "/tmp/$target";

        if (!file_exists($target)) {
          Terminus::error("Can't read database file %s", array($target));
        }

        Terminus::line("Unziping database");
        exec("gunzip $target", $stdout, $exit);

        // trim the gz of the target
        $target = Terminus\Utils\sql_from_zip($target);
        $target = escapeshellarg($target);

        if (!$database)
          $database = escapeshellarg(Terminus::prompt("Name of database to import to"));
        if (!$username)
          $username = escapeshellarg(Terminus::prompt("Username"));
        if (!$password)
          $password = escapeshellarg(Terminus::prompt("Password"));

        exec("mysql $database -u $username -p'$password' < $target", $stdout, $exit);
        if (0 != $exit) {
          Terminus::error("Could not import database");
        }

        Terminus::success("%s successfuly imported to %s", array($target, $database));
        return true;
        break;
      case 'create':
        if (!array_key_exists('element',$assoc_args)) {
          $assoc_args['element'] = Input::menu(array('code','db','files','all'), 'all', "Select element");
        }
        $result = $site->environment($env)->createBackup($assoc_args);
        if ($result) {
          Terminus::success("Created backup");
        } else {
          Terminus::error("Couldn't create backup.");
        }
        break;
      case 'list':
      case 'default':
        $backups = $site->environment($env)->backups();
        $element = @$assoc_args['element'];
        $data = array();
        foreach ($backups as $id => $backup) {
          if (!isset($backup->filename)) continue;
          $date = 'Pending';
          if (isset($backup->finish_time)) {
            $date = date("Y-m-d H:i:s", $backup->finish_time);
          }

          $size =  $backup->size / 1024 / 1024;
          if ($size > 0.1) {
            $size = sprintf("%.1fMB", $size);
          } elseif ($size > 0) {
            $size = "0.1MB";
          } else {
            $size = "0MB";
          }

          $data[] = array(
            $backup->filename,
            $size,
            $date,
          );
        }

        if (empty($backups)) {
          \Terminus::error("No backups found.");
          return false;
        } else {
          //munging data
          $this->handleDisplay($data, $args, array('File','Size','Date'));
          return $data;
        }
      break;
    }
   }

  /**
   * Init dev to test or test to live
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--env]
   * : Environment you want to initialize
   *
   * @subcommand init-env
   */
   public function init_env($args, $assoc_args) {
     $site = SiteFactory::instance(Input::sitename($assoc_args));
     $environments = array('dev', 'test', 'live');
     $env = $site->environment(Input::env(
       $assoc_args,
       'env',
       'Choose environment you want to initialize',
       array('test', 'live')
     ));

     $workflow = $env->initializeBindings();
     $workflow->wait();

     if($result) {
       \Terminus::success("Initialization complete!");
     }
     return true;
   }

  /**
   * Clone dev to test or test to live
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--from-env]
   * : Environment you want to clone from
   *
   * [--to-env]
   * : Environment you want to clone to
   *
   * [--db]
   * : Clone the database? (bool) default no
   *
   * [--files]
   * : Clone the files? (bool) default no
   *
   * @subcommand clone-env
   */
   public function clone_env($args, $assoc_args) {
     $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
     $site_id = $site->getId();
     $from_env = Input::env($assoc_args, 'from-env', "Choose environment you want to clone from");
     $to_env = Input::env($assoc_args, 'to-env', "Choose environment you want to clone to");

     $db = $files = false;
     $db = isset($assoc_args['db']) ?: false;
     $append = array();
     if ($db) {
       $append[] = "DATABASE";
     }
     $files = isset($assoc_args['files']) ?: false;
     if ($files) {
       $append[] = 'FILES';
     }
     $append = join(' and ', $append);

     if (!$files AND !$db) {
       \Terminus::error('You must specify something to clone using the the --db and --files flags');
     }

     $confirm = sprintf("Are you sure?\n\tClone from %s to %s\n\tInclude: %s\n", strtoupper($from_env), strtoupper($to_env), $append);
     \Terminus::confirm($confirm);

      if ( !$this->envExists($site_id, $to_env) ) {
        \Terminus::error("The %s environment was not found.", $to_env);
      }

     if ($db) {
       print "Cloning database ... ";
       $workflow = $site->workflows->create("clone_database", array(
         'environment' => $to_env,
         'params' => array('from_environment' => $from_env)
       ));
       $workflow->wait();
     }

     if ($files) {
      print "Cloning files ... ";
      $workflow = $site->workflows->create("clone_files", array(
        'environment' => $to_env,
        'params' => array('from_environment' => $from_env)
      ));
      $workflow->wait();
     }
     \Terminus::success("Clone complete!");
     return true;
   }

  /**
   * Create a MultiDev environment
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--env=<env>]
   * : Name of environment to create
   *
   * [--from-env=<env>]
   * : Environment clone content from, default = dev
   *
   * @subcommand create-env
   */
   public function create_env($args, $assoc_args) {
     $site = SiteFactory::instance(Input::sitename($assoc_args));

     if (isset($assoc_args['env'])) {
       $env = $assoc_args['env'];
     } else {
       $env = Terminus::prompt("Name of new MultiDev environment");
     }

     $site->environmentsCollection->fetch();
     $src = Input::env($assoc_args, 'from-env', "Environment to clone content from", $site->environmentsCollection->ids());

     $workflow = $site->createEnvironment($env, $src);
     $workflow->wait();
     Terminus::success("Created the $env environment");
   }

   /**
    * Merge a Multidev Environment into Dev Environment
    *
    * ## OPTIONS
    *
    * [--site=<site>]
    * : Site to use
    *
    * [--env=<env>]
    * : Name of multidev to environment to merge into Dev
    *
    * @subcommand merge-to-dev
    */
    public function merge_to_dev($args, $assoc_args) {
      $site = SiteFactory::instance(Input::sitename($assoc_args));
      $site->environmentsCollection->fetch();

      $multidev_ids = array_map(function($env) { return $env->id; }, $site->environmentsCollection->multidev());
      $multidev_id = Input::env($assoc_args, 'env', "Multidev environment to merge into Dev Environment", $multidev_ids);
      $environment = $site->environmentsCollection->get($multidev_id);

      $workflow = $environment->mergeToDev();
      $workflow->wait();

      Terminus::success(sprintf('Merged the %s environment into Dev', $environment->id));
    }

    /**
     * Merge the Dev Environment (Master) into a Multidev Environment
     *
     * ## OPTIONS
     *
     * [--site=<site>]
     * : Site to use
     *
     * [--env=<env>]
     * : Name of multidev to environment to merge Dev into
     *
     * @subcommand merge-from-dev
     */
     public function merge_from_dev($args, $assoc_args) {
       $site = SiteFactory::instance(Input::sitename($assoc_args));
       $site->environmentsCollection->fetch();

       $multidev_ids = array_map(function($env) { return $env->id; }, $site->environmentsCollection->multidev());
       $multidev_id = Input::env($assoc_args, 'env', "Multidev environment that the Dev Environment will be merged into", $multidev_ids);
       $environment = $site->environmentsCollection->get($multidev_id);

       $workflow = $environment->mergeFromDev();
       $workflow->wait();

       Terminus::success(sprintf('Merged the Dev environment into the %s environment ', $environment->id));
     }

   /**
   * Delete a MultiDev environment
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--env=<env>]
   * : name of environment to delete
   *
   * @subcommand delete-env
   */
   public function delete_env($args, $assoc_args) {
     $site = SiteFactory::instance(Input::sitename($assoc_args));
     $site->environmentsCollection->fetch();
     $multidev_envs = array_diff($site->environmentsCollection->ids(), array('dev', 'test', 'live'));
     $env = Input::env($assoc_args, 'env', "Environment to delete", $multidev_envs);

     Terminus::confirm("Are you sure you want to delete the '$env' environment from {$site->getName()}");

     $workflow = $site->deleteEnvironment($env);
     $workflow->wait();
     Terminus::success("Deleted the $env environment");
   }

   /**
    * Deploy dev environment to test or live
    *
    * ## OPTIONS
    *
    * [--site=<site>]
    * : Site to deploy from
    *
    * [--env=<env>]
    * : Environment to be deployed (Test or Live)
    *
    * [--clone-live-content]
    * : If deploying test, copy content from Live
    *
    * [--from=<env>]
    * : [deprecated] Environment to deploy from (non-functional)
    *
    * [--cc]
    * : Clear cache after deploy?
    *
    * [--updatedb]
    * : (Drupal only) run update.php after deploy?
    *
    * [--note=<note>]
    * : deploy log message
    *
    */
  public function deploy($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $env  = $site->environment(Input::env(
      $assoc_args,
      'env',
      'Choose environment to deploy'
    ));

    $clone_live_content = ($env->id == 'test' && isset($assoc_args['clone-live-content']));

    if(!isset($assoc_args['note'])) {
      $annotation = Terminus::prompt(
        'Custom note for the deploy log',
        array(),
        'Deploy from Terminus 2.0');
    } else {
      $annotation = $assoc_args['note'];
    }

    $cc       = (integer)array_key_exists('cc', $assoc_args);
    $updatedb = (integer)array_key_exists('updatedb', $assoc_args);

    $params = array(
      'updatedb'       => $updatedb,
      'clear_cache'    => $cc,
      'annotation'     => $annotation,
    );

    if ($clone_live_content) {
      $params['clone_database'] = array('from_environment' => 'live');
      $params['clone_files'] = array('from_environment' => 'live');
    }

    $workflow = $env->deploy($params);
    $workflow->wait();

    if($workflow->isSuccessful()) {
      \Terminus::success("Woot! Code deployed to %s", array($env->getName()));
    }
  }

  /**
   * List enviroments for a site
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Name of site to check
   *
   */
  function environments($args, $assoc_args) {
    $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
    $site->environmentsCollection->fetch();
    $environments = $site->environmentsCollection->all();

    $data = array();
    foreach ($environments as $env) {
      $data[] = array(
        'Name' => $env->id,
        'Created' => $env->environment_created,
        'Domain' => $env->domain(),
        'OnServer Dev?' => $env->on_server_development ? 'true' : 'false',
        'Locked?' => $env->lock->locked ? 'true' : 'false',
      );
    }
    $this->handleDisplay($data, $args);
    return $data;
  }

   private function envExists($site_id, $env) {
     $response = \Terminus_Command::request('sites', $site_id, 'environments', 'GET');
     $envs = (array) $response['data'];
     return array_key_exists($env, $envs);
   }

  /**
   * Hostname operations
   *
   * ## OPTIONS
   *
   * <list|add|remove>
   * : OPTIONS are list, add, delete
   *
   * [--site=<site>]
   * : Site to use
   *
   * --env=<env>
   * : environment to use
   *
   * [--hostname=<hostname>]
   * : hostname to add
   *
   */
   public function hostnames($args, $assoc_args) {
     $action = array_shift($args);
     $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
     $env = Input::env($assoc_args, 'env');
     switch ($action) {
       case 'list':
        $hostnames = $data = (array) $site->environment($env)->hostnames();
        if (!Terminus::get_config('json')) {
          // if were not just dumping the json then we should reformat the data
          $data = array();
          foreach ($hostnames as $hostname => $details ) {
            $data[] = array_merge( array('domain' => $hostname), (array) $details);
          }
        }
        $this->handleDisplay($data);
        break;
       case 'add':
          if (!isset($assoc_args['hostname'])) {
            Terminus::error("Must specify hostname with --hostname");
          }
          $data = $site->environment($env)->hostnameadd($assoc_args['hostname']);
          if (Terminus::get_config('verbose')) {
            \Terminus\Utils\json_dump($data);
          }
          Terminus::success("Added %s to %s-%s", array( $assoc_args['hostname'], $site->getName(), $env));
          break;
       case 'remove':
          if (!isset($assoc_args['hostname'])) {
            Terminus::error("Must specify hostname with --hostname");
          }
          $data = $site->environment($env)->hostnamedelete($assoc_args['hostname']);
          Terminus::success("Deleted %s from %s-%s", array( $assoc_args['hostname'], $site->getName(), $env));
        break;
     }
     return $data;
   }

  /**
   * Lock an environment to prevent changes
   *
   * ## OPTIONS
   *
   * <info|add|remove>
   * : action to execute ( i.e. info, add, remove )
   *
   * [--site=<site>]
   * : site name
   *
   * [--env=<env>]
   * : site environment
   *
   * [--username=<username>]
   * : your username
   *
   * [--password=<password>]
   * : your password
   *
  **/
  function lock($args, $assoc_args) {
    $action = array_shift($args);
    $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
    $env = Input::env($assoc_args, 'env');
    switch ($action) {
      case 'info':
        $info = $site->environment($env)->lockinfo();
        return $this->handleDisplay($info);
      case 'add':
        Terminus::line("Creating new lock on %s -> %s", array($site->getName(), $env));
        if (!isset($assoc_args['username'])) {
          $username = Terminus::prompt("Username for the lock");
        } else {
          $username = $assoc_args['username'];
        }
        if (!isset($assoc_args['password'] ) ) {
          exec("stty -echo");
          $password = Terminus::prompt( "Password for the lock" );
          exec("stty echo");
          Terminus::line();
        } else {
          $password = $assoc_args['password'];
        }

        $workflow = $site->environment($env)->lock(array(
          'username' => $username,
          'password' => $password
        ));
        $workflow->wait();
        return Terminus::success('Success');
      case 'remove':
        Terminus::line("Removing lock from %s -> %s", array($site->getName(), $env));
        $workflow = $site->environment($env)->unlock();
        $workflow->wait();
        return Terminus::success('Success');
    }
  }

  /**
   * Import a zip archive == see this article for more info:
   * http://helpdesk.getpantheon.com/customer/portal/articles/1458058-importing-a-wordpress-site
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--url=<url>]
   * : URL of archive to import
   *
   * [--element=<element>]
   * : Site element to import (i.e. code, files, db, or all)
   *
   * @subcommand import
   */
  public function import($args, $assoc_args) {
    $site = SiteFactory::instance( Input::sitename( $assoc_args ) );
    $url = Input::string($assoc_args, 'url', "URL of archive to import");
    if (!$url) {
      Terminus::error("Please enter a URL.");
    }

    if(!isset($assoc_args['element'])) {
      $element_options = array('code', 'database', 'files', 'all');
      $element_key = Input::menu($element_options, 'all', 'Which element are you importing?');
      $element = $element_options[$element_key];
    } else {
      $element = $assoc_args['element'];
    }

    $workflow = $site->import($url, $element);
    Terminus::line('Import started, you can now safely kill this script without interfering.');
    $workflow->wait();
    Terminus::success("Import complete");
  }

  /**
   * Change the site payment instrument
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--instrument=<UUID>]
   * : Change the instrument by setting the ID
   *
   * ## EXAMPLES
   *
   *  terminus site instrument --site=sitename
   */
  public function instrument($args, $assoc_args) {
    $user = new User();
    $instruments = $user->instruments()->all();
    foreach($instruments as $instrument) {
      $data[$instrument->get('id')] = $instrument->get('label');
    }

    //If site is not set, show all user's payment instruments
    if(!isset($assoc_args['site'])) {
      $this->handleDisplay($data, array(), array('UUID', 'Label'));
    } else {
      array_unshift($data, 'none');
      $site = SiteFactory::instance($assoc_args['site']);
      //If instrument is not present, show the site's current instrument
      if(!isset($assoc_args['instrument'])) {
        $instrument_uuid = $site->get('instrument');
        if($instrument_uuid == null) {
          \Terminus::line(
            $site->get('name') . ' does not have an attached payment instrument.'
          );
        } else {
          \Terminus::line(
            $site->get('name') . ' is being charged to ' . $data[$instrument_uuid]
            . ', UUID: ' . $instrument_uuid
          );
        }
      } else {
        //Both are present. Ensure sure UUID is valid.
        //This attempts to prevent users from selecting instruments which do not belong to them.
        $instrument_id = $assoc_args['instrument'];
        if(!isset($data[$instrument_id])) {
          $location = array_search($instrument_id, $data);
          if($location !== false) {
            $instrument_id = $location;
          } else {
            $uuids          = array_keys($data);
            $instrument_id = Input::menu(
              $data,
              null,
              'Select a payment instrument'
            );
          }
        }
        //Change the instrument once we have a valid instrument.
        if($instrument_id == 0) {
          $workflow = $site->removeInstrument();
        } else {
          $workflow = $site->addInstrument($instrument_id);
        }
        $workflow->wait();

        \Terminus::line("Successfully updated payment instrument to $instrument_id.");
      }
    }
  }

  /**
   * List a site's job history
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to deploy from
  **/
  public function jobs($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $jobs = $site->jobs();
    $data = array();
    foreach ($jobs as $job) {
      $data[] = array(
        'slot' => $job->slot,
        'name' => $job->human_name,
        'env'  => @$job->environment,
        'status'  => $job->status,
        'updated' => $job->changed
      );
    }
    $this->handleDisplay($data,$args);
  }

  /**
   * Mount a site with sshfs
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to deploy from
   *
   * --destination=<path>
   * : local directory to mount
   *
   * [--env=<env>]
   * : Environment (dev,test)
   *
  **/
  public function mount($args, $assoc_args) {
    exec("which sshfs", $stdout, $exit);
    if ($exit !== 0) {
      Terminus::error("Must install sshfs first");
    }

    $destination = \Terminus\Utils\destination_is_valid($assoc_args['destination']);

    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $env = Input::env($assoc_args, 'env');

    // Darwin check ... not sure what this is really ... borrowed from terminus 1
    $darwin = false;
    exec('uname', $output, $ret);
    if (is_array($output) && isset($output[0]) && strpos($output[0], 'Darwin') !== False) {
     $darwin = True;
    }

    // @todo I'd prefer this was done with sprintf for a little validation
    $user = $env.'.'.$site->getId();
    $host = 'appserver.' . $env . '.' . $site->getId() . '.drush.in';
    $darwin_args = $darwin ? '-o defer_permissions ' : '';
    $cmd = "sshfs " . $darwin_args . "-p 2222 {$user}@{$host}:./ {$destination}";
    exec($cmd, $stdout, $exit);
    if ($exit !== 0) {
      print_r($stdout);
      Terminus::error("Couldn't mount $destination");
    }
    Terminus::success("Site mounted to %s. To unmount, run: umount %s ( or fusermount -u %s ).", array($destination,$destination,$destination));
  }

  /**
  * Get New Relic Info for site
  *
  * ## OPTIONS
  *
  * [--site=<site>]
  * : site for which to retreive notifications
  *
  * @subcommand new-relic
  */
  public function new_relic($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $data = $site->newRelic();
    if($data) {
      $this->handleDisplay($data->account,$assoc_args,array('Key','Value'));
    } else {
      Logger::coloredOutput("%YNew Relic is not enabled.%n");
    }
  }

  /**
  * Open the Pantheon site dashboard a browser
  *
  * ## OPTIONS
  *
  * [--site=<site>]
  * : site for which to retreive notifications
  *
  */
  public function notifications($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $notifications = $site->notifications();
    $data = array();
    foreach ($notifications as $note) {
      $data[] = array(
        'time'  => $note->start,
        'name'  => $note->name,
        'id'    => $note->build->number."@".$note->build->environment->HOSTNAME,
        'status'=> $note->build->status,
        'phase' => $note->build->phase,
        'duration' => $note->build->estimated_duration,
      );
    }
    $this->handleDisplay($data);
  }

  /**
  * Get or set owner
  *
  * ## OPTIONS
  *
  * [--site=<site>]
  * : Site to check
  *
  * [--set=<value>]
  * : new owner to set
  *
  * @subcommand owner
  */
  public function owner($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $data = $site->owner();
    $this->handleOutput($data);
  }

  /**
   * Interacts with redis
   *
   * ## OPTIONS
   *
   * <clear>
   * : clear - Clear redis cache on remote server
   *
   * [--site=<site>]
   * : site name
   *
   * [--env=<env>]
   * : environment
   *
   * ## Examples
   *
   *    terminus site redis clear --site=mikes-wp-test --env=live
   *
   */
  public function redis($args, $assoc_args) {
    $action = array_shift($args);
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $env = @$assoc_args['env'];
    switch ($action) {
      case 'clear':
        $bindings = $site->bindings('cacheserver');
        if (empty($bindings)) {
          \Terminus::error("Redis cache not enabled");
        }
        $commands = array();
        foreach($bindings as $binding) {
          if ( @$env AND $env != $binding->environment) continue;
          // @$todo ... should probably do this with symfony Process lib
          $args = array( $binding->environment, $site->getId(), $binding->environment, $site->getId(), $binding->host, $binding->port, $binding->password );
          array_filter($args, function($a) { return escapeshellarg($a); });
          $commands[$binding->environment] = vsprintf(
            'ssh -p 2222 %s.%s@appserver.%s.%s.drush.in "redis-cli -h %s -p %s -a %s flushall"',
            $args
          );
        }
        foreach ($commands as $env => $command) {
          Terminus::line("Clearing redis on %s ", array($env));
          exec($command, $stdout, $return);
          echo Logger::greenLine($stdout[0]);
        }
        break;
    }
  }

  /**
   * Get or set service level
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to check
   *
   * [--set=<value>]
   * : new service level to set
   *
   * @subcommand service-level
   */
  public function service_level($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $info = $site->info('service_level');
    if (isset($assoc_args['set'])) {
      $set = $assoc_args['set'];
      $data = $site->updateServiceLevel($set);
      Logger::coloredOutput("%2<K>Service Level has been updated to '$set'%n");
    }
    Logger::coloredOutput("%2<K>Service Level is '$info'%n");
    return true;
  }

  /**
  * Get or set team members
  *
  * ## OPTIONS
  *
  * <list|add-member|remove-member>
  * : i.e. add or remove
  *
  * [--site=<site>]
  * : Site to check
  *
  * [--member=<email>]
  * : Email of the member to add. Member will receive an invite
  *
  * @subcommand team
  */
  public function team($args, $assoc_args) {
    $action = array_shift($args) ?: 'list';
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $data = array();
    switch($action) {
      case 'add-member':
        $team = $site->teamAddMember($assoc_args['member']);
        Logger::coloredOutput("%2<K>Team member added!</K>");
        break;
      case 'remove-member':
        $team = $site->teamRemoveMember($assoc_args['member']);
        Logger::coloredOutput("%2<K>Team member removed!</K>");
        break;
      case 'list':
      default:
        $team = $site->team();
        foreach ($team as $uuid => $user) {
          $data[] = array(
            'First' => $user->user->profile->firstname,
            'Last'  => $user->user->profile->lastname,
            'Email' => $user->user->email,
            'UUID'  => $user->user->id,
          );
        }
        ksort($data);
        break;
    }
    if (!empty($data)) {
      $this->handleDisplay($data);
    }
  }

  /**
   * Show upstream updates
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to check
   *
   * @subcommand upstream-info
   */
  public function upstream_info($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $upstream = $site->getUpstream();
    $this->handleDisplay($upstream,$args);
  }

  /**
   * Show or apply upstream updates
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to check
   *
   * [--update=<env>]
   * : Do update on dev env
   *
   * @alias upstream-updates
  **/
   public function upstream_updates($args, $assoc_args) {
     $site = SiteFactory::instance(Input::sitename($assoc_args));
     $upstream = $site->getUpstreamUpdates();

     // data munging as usual
     $data = array();

     if(isset($upstream->remote_url) && isset($upstream->behind)) {
       // The $upstream object returns a value of [behind] -> 1 if there is an
       // upstream update that has not been applied to Dev.
       $data[$upstream->remote_url] = ($upstream->behind > 0 ? "Updates Available":"Up-to-date");

       $this->_constructTableForResponse($data, array('Upstream','Status') );
       if (!isset($upstream) OR empty($upstream->update_log)) Terminus::success("No updates to show");
       $upstreams = (array) $upstream->update_log;
       if (!empty($upstreams)) {
         $data = array();
         foreach ($upstreams as $commit) {
           $data = array(
             'hash' => $commit->hash,
             'datetime'=> $commit->datetime,
             'message' => $commit->message,
             'author' => $commit->author,
           );
           $this->handleDisplay($data,$args);
           echo PHP_EOL;
         }
       }
     } else {
       $this->handleDisplay('There was a problem checking your upstream status. Please try again.');
       echo PHP_EOL;
     }
     if (isset($assoc_args['update']) AND !empty($upstream->update_log)) {
       $env = 'dev';
       Terminus::confirm(sprintf("Are you sure you want to apply the upstream updates to %s-dev", $site->getName(), $env));
       $response = $site->applyUpstreamUpdates($env);
       if (@$response->id) {
         $this->waitOnWorkflow('sites', $site->getId(), $response->id);
       } else {
         Terminus::success("Updates applied");
       }
     }

   }

  /**
   * Pings a site to ensure it responds
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : site to ping
   *
   * [--env=<env>]
   * : environment to ping
   *
   * ## Examples
   *  terminus site wake --site='testsite' --env=dev
  */
  public function wake($args, $assoc_args) {
    $site = SiteFactory::instance(Input::sitename($assoc_args));
    $env = Input::env($assoc_args, 'env');
    $data = $site->environment($env)->wake();
    if (!$data['success']) {
      Logger::redLine(sprintf("Could not reach %s", $data['target']));
      return;
    }

    if (!$data['styx']) {
      Logger::redLine("Pantheon headers missing, which isn't quite right.");
      return;
    }

    Logger::greenLine(sprintf( "OK >> %s responded in %s", $data['target'], $data['time']));

  }

  /**
   * Complete wipe and reset a site
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to use
   *
   * [--env=<env>]
   * : Specify environment, default = dev
   */
   public function wipe($args, $assoc_args) {
     try {
       $env = @$assoc_args['env'] ?: 'dev';
       $site = SiteFactory::instance(Input::sitename($assoc_args));
       $site_id = $site->getId();
       $env = Input::env($assoc_args, 'env');
       Terminus::line("Wiping %s %s", array($site_id, $env));
       $resp = $site->environment($env)->wipe();
       if ($resp) {
         $this->waitOnWorkflow('sites', $site_id, $resp['data']->id);
         Terminus::success("Successfully wiped %s -- %s", array($site->getName(),$env));
       }
    } catch(Exception $e) {
      Terminus::error("%s",array($e->getMessage()));
    }
   }

   /**
    * Delete a site from pantheon
    *
    * ## OPTIONS
    * [--site=<site>]
    * : ID of the site you want to delete
    *
    * [--force]
    * : to skip the confirmations
    */
   function delete($args, $assoc_args) {
     $sitename = Input::sitename($assoc_args);
     $site_id = $this->sitesCache->findID($sitename);
     $site_to_delete = new Site($site_id);

     if (!isset($assoc_args['force']) AND !Terminus::get_config('yes')) {
       // if the force option isn't used we'll ask you some annoying questions
       Terminus::confirm( sprintf( "Are you sure you want to delete %s?", $site_to_delete->information->name ));
       Terminus::confirm( "Are you really sure?" );
     }
     Terminus::line( sprintf( "Deleting %s ...", $site_to_delete->information->name ) );
     $response = \Terminus_Command::request( 'sites', $site_to_delete->id, '', 'DELETE' );

     $this->sitesCache->remove($sitename);
     Terminus::success("Deleted %s!", $sitename);
   }
}

\Terminus::add_command( 'site', 'Site_Command' );
