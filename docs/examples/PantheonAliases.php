<?php

require(__DIR__ . '/vendor/autoload.php');

use Terminus\Auth;
use Terminus\Exceptions\TerminusException;
use Terminus\Models\User;
use Terminus\Models\Collections\Sites;
use Terminus\Request;
use Terminus\Session;

class PantheonAliases
{

  /**
   * Object constructor
   *
   * @param string[] $arg_options Elements as follow:
   *        string username Your Pantheon username
   *        string password Your Pantheon password
   *        string location Location for your aliases file
   * @return PantheonAliases
   */
    public function __construct(array $arg_options = [])
    {
        $default_options = [
        'username' => null,
        'password' => null,
        'location' => getenv('HOME') . '/.drush/pantheon.aliases.drushrc.php',
        ];
        $options = array_merge($default_options, $arg_options);

        $terminus = new Terminus();
        $this->logIn($options);
        $this->writeAliases($options['location']);
    }

  /**
   * Constructs a Drush alias for an environment. Used to supply
   *   organizational Drush aliases not provided by the API.
   *
   * @param Environment $environment Environment to create an alias for
   * @return string
   * @throws TerminusException
   */
    private function constructAlias($environment)
    {
        $site_name   = $environment->site->get('name');
        $site_id     = $environment->site->get('id');
        $env_id      = $environment->get('id');
        $env_vars = $environment->fetchEnvironmentVars();
        $hostnames   = array_keys((array)$environment->getHostnames());
        if (empty($hostnames) || empty($env_vars)) {
            throw new TerminusException(
                'No hostname entry for {site}.{env}',
                ['site' => $site_name, 'env' => $env_id,],
                1
            );
        }
        $uri        = array_shift($hostnames);
        $db_user    = $env_vars['DB_USER'] ?? null;
        $db_pass    = $env_vars['DB_PASSWORD'] ?? null;
        $db_port    = $env_vars['DB_PORT'] ?? null;
        if (strpos(TERMINUS_HOST, 'onebox') !== false) {
            $remote_user = "appserver.$env_id.$site_id";
            $remote_host = TERMINUS_HOST;
            $db_url      = "mysql://$db_user:$db_pass@$remote_host:$db_port";
            $db_url     .= '/pantheon';
        } else {
            $remote_user = "$env_id.$site_id";
            $remote_host = "appserver.$env_id.$site_id.drush.in";
            $db_url      = "mysql://$db_user:$db_pass@dbserver.$environment.$site_id";
            $db_url     .= ".drush.in:$db_port/pantheon";
        }
        $output = "array(
    'uri'              => $uri,
    'db-url'           => $db_url,
    'db-allows-remote' => true,
    'remote-host'      => $remote_host,
    'remote-user'      => $remote_user,
    'ssh-options'      => '-p 2222',
    'path-aliases'     => array(
      '%files'        => 'code/sites/default/files',
      '%drush-script' => 'drush',
    ),
  );";
        return $output;
    }

  /**
   * Requests API data and returns aliases
   *
   * @return string
   */
    private function getAliases()
    {
        $request  = new Request();
        $user     = new User();
        $path     = 'drush_aliases';
        $method   = 'GET';
        $response = $request->request(
            'users',
            Session::getValue('user_id'),
            $path,
            $method
        );
        eval(str_replace('<?php', '', $response['data']->drush_aliases));
        $formatted_aliases = substr($response['data']->drush_aliases, 0, -1);
        $sites_object = new Sites();
        $sites        = $sites_object->all();
        foreach ($sites as $site) {
            $environments = $site->environments->all();
            foreach ($environments as $environment) {
                $key = $site->get('name') . '.'. $environment->get('id');
                if (isset($aliases[$key])) {
                    break;
                }
                try {
                    $formatted_aliases .= PHP_EOL . "  \$aliases['$key'] = ";
                    $formatted_aliases .= $this->constructAlias($environment);
                } catch (TerminusException $e) {
                    continue;
                }
            }
        }
        $formatted_aliases .= PHP_EOL;
        return $formatted_aliases;
    }

  /**
   * Logs you in to Pantheon
   *
   * @param string[] $options Elements as follow:
   *        string username Your Pantheon username
   *        string password Your Pantheon password
   * @return void
   * @throws TerminusException
   */
    private function logIn(array $options)
    {
        if (is_null($options['username']) || is_null($options['password'])) {
            throw new TerminusException(
                'You need to supply both a username and a password to the constructor.'
            );
        }

        $auth = new Auth();
        $auth->logInViaUsernameAndPassword(
            $options['username'],
            $options['password']
        );
    }

  /**
   * Retrieves and writes Pantheon aliases to a file
   *
   * @param string $location Name of the file to which to write aliases
   * @return bool
   */
    private function writeAliases($location)
    {
        $logger = Terminus::getLogger();
        if (is_dir($location)) {
            $message  = 'Please provide a full path with filename,';
            $message .= ' e.g. {location}/pantheon.aliases.drushrc.php';
            $logger->error($message, compact('location'));
        }

        $file_exists = file_exists($location);

        // Create the directory if it doesn't yet exist
        $dirname = dirname($location);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0700, true);
        }

        $content = $this->getAliases();
        $handle  = fopen($location, 'w+');
        fwrite($handle, $content);
        fclose($handle);
        chmod($location, 0700);

        $message = 'Pantheon aliases created';
        if ($file_exists) {
            $message = 'Pantheon aliases updated';
        }
        $logger->info($message);
    }
}
