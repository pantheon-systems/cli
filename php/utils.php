<?php

namespace Terminus\Utils;

use ArrayIterator;
use Symfony\Component\Yaml\Yaml;
use Terminus;
use Terminus\Request;
use Terminus\Helpers\Input;
use Terminus\Iterators\Transform;
use Terminus\Exceptions\TerminusException;

if (!defined('JSON_PRETTY_PRINT')) {
  define('JSON_PRETTY_PRINT', 128);
}

/**
 * Composes associative arguments into a command string
 *
 * @param array $assoc_args Arguments for command line in array form
 * @return string Command string form of param
 */
function assocArgsToStr($assoc_args) {
  $return = '';

  foreach ($assoc_args as $key => $value) {
    if ($value === true) {
      $return .= " --$key";
    } else {
      $return .= " --$key=" . escapeshellarg($value);
    }
  }

  return $return;
}

/**
  * Retrieves current version number from repository and saves it to the cache
  *
  * @return string The version number
  */
function checkCurrentVersion() {
  $request  = new Request();
  $url      = 'https://api.github.com/repos/pantheon-systems/cli/releases';
  $url     .= '?per_page=1';
  $response = $request->simpleRequest($url, array('absolute_url' => true));
  $release  = array_shift($response['data']);
  Terminus::getCache()->putData(
    'latest_release',
    array('version' => $release->name, 'check_date' => time())
  );
  return $release->name;
}

/**
  * Checks for new versions of Terminus once per week and saves to cache
  *
  * @return void
  */
function checkForUpdate() {
  $cache_data = Terminus::getCache()->getData(
    'latest_release',
    array('decode_array' => true)
  );
  if (!$cache_data
    || ((int)$cache_data['check_date'] < (int)strtotime('-7 days'))
  ) {
    $logger = Terminus::getLogger();
    try {
      $current_version = checkCurrentVersion();
      if (version_compare($current_version, TERMINUS_VERSION, '>')) {
        $logger->info(
          'An update to Terminus is available. Please update to {version}.',
          array('version' => $current_version)
        );
      }
    } catch (\Exception $e) {
      $logger->info($e->getMessage());
      $logger->info('Cannot retrieve current Terminus version.');
    }
  }
}

/**
 * Returns a colorized string
 *
 * @param string $string Message to colorize for output
 * @return string
 */
function colorize($string) {
  $colorize = true;
  if (Terminus::getConfig('colorize') == 'auto') {
    $colorize = !\cli\Shell::isPiped();
  }
  $colorized_string = \cli\Colors::colorize(
    $string,
    $colorize
  );
  return $colorized_string;
}

/**
 * Sets constants necessary for the proper functioning of Terminus
 *
 * @return void
 */
function defineConstants() {
  define('Terminus', true);
  $default_constants = Yaml::parse(
    file_get_contents(TERMINUS_ROOT . '/config/constants.yml')
  );
  foreach ($default_constants as $var_name => $default) {
    if (isset($_SERVER[$var_name]) && ($_SERVER[$var_name] != '')) {
      define($var_name, $_SERVER[$var_name]);
    } else if (!defined($var_name)) {
      define($var_name, $default);
    }
  }
  date_default_timezone_set(TERMINUS_TIME_ZONE);

  if (!defined('TERMINUS_SCRIPT')) {
    define('TERMINUS_SCRIPT', 'php/Terminus.php');
  }
}

/**
 * Ensures that the given destination is valid
 *
 * @param string $destination Location of directory to ensure viability of
 * @param bool   $make        True to create destination if it does not exist
 * @return string Same as the parameter
 * @throws TerminusException
 */
function destinationIsValid($destination, $make = true) {
  if (file_exists($destination) AND !is_dir($destination)) {
    throw new TerminusException(
      'Destination given is a file. It must be a directory.'
    );
  }

  if (!is_dir($destination)) {
    if (!$make) {
      $make = Input::confirm(
        array('message' => 'Directory does not exists. Create it now?')
      );
    }
    if ($make) {
      mkdir($destination, 0755);
    }
  }

  return $destination;
}

/**
 * Get file name from a URL
 *
 * @param string $url A valid URL
 * @return string The file name from the given URL
 */
function getFilenameFromUrl($url) {
  $path     = parseUrl($url);
  $parts    = explode('/', $path['path']);
  $filename = end($parts);
  return $filename;
}

/**
 * Return an array of paths where vendor autoload files may be located
 *
 * @return array
 */
function getVendorPaths() {
  $vendor_paths = array(
    TERMINUS_ROOT . '/../../../vendor',
    TERMINUS_ROOT . '/vendor'
  );
  return $vendor_paths;
}

/**
 * Imports environment variables from all files ending in '.env'
 *
 * @return void
 */
function importEnvironmentVariables() {
  //Load environment variables from __DIR__/*.env
  $files = array_filter(
    scandir(getcwd()),
    function($file) {
      preg_match("/.env$/", $file, $matches);
      $is_env = !empty($matches);
      return $is_env;
    }
  );
  foreach ($files as $file) {
    $env = new \Dotenv\Dotenv(getcwd(), $file);
    $env->load();
  }
}

/**
 * Checks given path for whether it is absolute
 *
 * @param string $path Path to check
 * @return bool True if path is absolute
 */
function isPathAbsolute($path) {
  $is_root = (isset($path[1]) && ($path[1] == ':') || ($path[0] == '/'));
  return $is_root;
}

/**
 * Checks whether email is in a valid or not
 *
 * @param string $email String to be evaluated for email address format
 * @return bool True if $email is in email address format
 */
function isValidEmail($email) {
  $is_email = !is_bool(filter_var($email, FILTER_VALIDATE_EMAIL));
  return $is_email;
}

/**
 * Check whether Terminus is running in a Windows environment
 *
 * @return bool True if OS running Terminus is Windows
 */
function isWindows() {
  $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
  return $is_windows;
}

/**
 * Includes every command file in the commands directory
 *
 * @return void
 */
function loadAllCommands() {
  $cmd_dir = TERMINUS_ROOT . '/php/Terminus/Commands';

  $iterator = new \DirectoryIterator($cmd_dir);

  foreach ($iterator as $filename) {
    if (substr($filename, -4) != '.php') {
      continue;
    }

    include_once "$cmd_dir/$filename";
  }
}

/**
 * Loads a file of the given name from the assets directory.
 *
 * @param string $file Relative file path from the assets dir
 * @return string Contents of the asset file
 * @throws TerminusException
 */
function loadAsset($file) {
  $asset_location = sprintf('%s/assets/%s', TERMINUS_ROOT, $file);
  /**
   * The warning reporting is disabled because missing files will both issue
   * warnings and return false, and we cannot just catch the warning such as
   * things are currently set.
   */
  error_reporting(E_ALL ^ E_WARNING);
  $asset_file = file_get_contents($asset_location);
  error_reporting(E_ALL);

  if (!$asset_file) {
    throw new TerminusException(
      'Terminus could not locate an asset file at {asset_location}',
      compact('asset_location'),
      1
    );
  }
  return $asset_file;
}

/**
 * Includes a single command file
 *
 * @param string $name Of command of which the class file will be included
 * @return void
 */
function loadCommand($name) {
  $path = sprintf(
    '%s/php/Terminus/Commands/%sCommand.php',
    TERMINUS_ROOT,
    ucwords($name)
  );

  if (is_readable($path)) {
    include_once $path;
  }
}

/**
 * Requires inclusion of Composer's autoload file
 *
 * @return void
 */
function loadDependencies() {
  if (strpos(TERMINUS_ROOT, 'phar:') === 0) {
    require TERMINUS_ROOT . '/vendor/autoload.php';
    return;
  }

  $has_autoload = false;

  foreach (getVendorPaths() as $vendor_path) {
    if (file_exists($vendor_path . '/autoload.php')) {
      require $vendor_path . '/autoload.php';
      $has_autoload = true;
      break;
    }
  }

  if (!$has_autoload) {
    fputs(STDERR, "Internal error: Can't find Composer autoloader.\n");
    exit(3);
  }
}

/**
 * Using require() directly inside a class grants access to private methods
 * to the loaded code
 *
 * @param string $path Path to the file to be required
 * @return void
 */
function loadFile($path) {
  require $path;
}

/**
 * Takes a host string such as from wp-config.php and parses it into an array
 *
 * @param string $raw_host MySQL host string, as defined in wp-config.php
 * @return array $assoc_args Connection inforrmation for MySQL
 */
function mysqlHostToCliArgs($raw_host) {
  $assoc_args = array();

  $host_parts = explode(':', $raw_host);
  if (count($host_parts) == 2) {
    list($assoc_args['host'], $extra) = $host_parts;
    $extra = trim($extra);
    if (is_numeric($extra)) {
      $assoc_args['port']     = intval($extra);
      $assoc_args['protocol'] = 'tcp';
    } elseif ($extra !== '') {
      $assoc_args['socket'] = $extra;
    }
  } else {
    $assoc_args['host'] = $raw_host;
  }

  return $assoc_args;
}

/**
 * Parses a URL and returns its components
 *
 * @param string $url URL to parse
 * @return array An array of URL components
 */
function parseUrl($url) {
  $url_parts = parse_url($url);

  if (!isset($url_parts['scheme'])) {
    $url_parts = parse_url('http://' . $url);
  }

  return $url_parts;
}

/**
 * Sanitize the site name field
 *
 * @param string $string String to be sanitized
 * @return string Param string, sanitized
 */
function sanitizeName($string) {
  $name = $string;
  // squash whitespace
  $name = trim(preg_replace('#\s+#', ' ', $name));
  // replace spacers with hyphens
  $name = preg_replace("#[\._ ]#", "-", $name);
  // crush everything else
  $name = strtolower(preg_replace("#[^A-Za-z0-9-]#", "", $name));
  return $name;
}

/**
 * Removes ".gz" from a filename
 *
 * @param string $filename Name of file from which to remove ".gz"
 * @return string Param string, ".gz" removed
 */
function sqlFromZip($filename) {
  $file = preg_replace('#\.gz$#s', '', $filename);
  return $file;
}

/**
  * Strips sensitive data out of the JSON printed in a request string
  *
  * @param array $request   Elements as follows:
  *        string url     URL for the request
  *        string method  Method for request (e.g. PUT, POST)
  *        array  options Settings for the request
  * @param array $blacklist Array of string keys to remove from request
  * @return string Sensitive data-stripped version of $request_data
  */
function stripSensitiveData($request, $blacklist = array()) {
  //Locate the JSON in the string, turn to array
  $regex = '~\{(.*)\}~';
  preg_match($regex, $request, $matches);
  if (empty($matches)) {
    return $request;
  }
  $request_array = json_decode($matches[0], true);

  //See if a blacklisted items are in the arrayed JSON, replace
  foreach ($blacklist as $blacklisted_item) {
    if (isset($request_array[$blacklisted_item])) {
      $request_array[$blacklisted_item] = '*****';
    }
  }
  return $request_data;
}

/**
 * Render PHP or other types of files using Twig templates
 *
 * @param string $template_name File name of the template to be used
 * @param array  $data          Context to pass through for template use
 * @param array  $options       Options to pass through for template use
 * @return string The rendered template
 */
function twigRender($template_name, $data, $options) {
  $loader            = new \Twig_Loader_Filesystem(TERMINUS_ROOT . '/templates');
  $twig              = new \Twig_Environment($loader);
  $rendered_template = $twig->render(
    $template_name,
    array(
      'data'          => $data,
      'template_name' => $template_name,
      'options'       => $options,
    )
  );
  return $rendered_template;
}
