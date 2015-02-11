<?php

namespace Terminus\Utils;

use \Terminus\Dispatcher;
use \Terminus\Iterators\Transform;
use \ArrayIterator;

if (!defined('JSON_PRETTY_PRINT')){
  define('JSON_PRETTY_PRINT', 128);
}

function load_dependencies() {
  if ( 0 === strpos( TERMINUS_ROOT, 'phar:' ) ) {
    require TERMINUS_ROOT . '/vendor/autoload.php';
    return;
  }

  $has_autoload = false;

  foreach ( get_vendor_paths() as $vendor_path ) {
    if ( file_exists( $vendor_path . '/autoload.php' ) ) {
      require $vendor_path . '/autoload.php';
      $has_autoload = true;
      break;
    }
  }

  if ( !$has_autoload ) {
    fputs( STDERR, "Internal error: Can't find Composer autoloader.\n" );
    exit(3);
  }
}

function get_vendor_paths() {
  return array(
    TERMINUS_ROOT . '/../../../vendor',  // part of a larger project / installed via Composer (preferred)
    TERMINUS_ROOT . '/vendor',           // top-level project / installed as Git clone
  );
}

// Using require() directly inside a class grants access to private methods to the loaded code
function load_file( $path ) {
  require $path;
}

function load_command( $name ) {
  $path = TERMINUS_ROOT . "/php/commands/$name.php";

  if ( is_readable( $path ) ) {
    include_once $path;
  }
}

function load_all_commands() {
  $cmd_dir = TERMINUS_ROOT . '/php/commands';

  $iterator = new \DirectoryIterator( $cmd_dir );

  foreach ( $iterator as $filename ) {
    if ( '.php' != substr( $filename, -4 ) ){
      continue;
    }

    include_once "$cmd_dir/$filename";
  }
}

function json_dump($var) {
  // if it's a piped command, don't 'prettify' json.
  if (\cli\Shell::isPiped()) {
    return json_encode( $var );
  } else {
    // if not, make it legible to humans
    return json_encode( $var , JSON_PRETTY_PRINT )."\n";
  }
}

function bash_out($var) {
  // if it's a piped command, don't 'prettify' json.
  $output = '';
  foreach( $var as $index => $row ) {
    if( is_array($row) OR is_object($row) ) {
      $row = (array) $row;
      $row = join(' ',$row);
    }
    if (!is_numeric($index))
      $output .= "$index ";
    $output .= $row.PHP_EOL;
  }
  return $output;
}


/**
 * Like array_map(), except it returns a new iterator, instead of a modified array.
 *
 * Example:
 *
 *     $arr = array('Football', 'Socker');
 *
 *     $it = iterator_map($arr, 'strtolower', function($val) {
 *       return str_replace('foo', 'bar', $val);
 *     });
 *
 *     foreach ( $it as $val ) {
 *       var_dump($val);
 *     }
 *
 * @param array|object Either a plain array or another iterator
 * @param callback The function to apply to an element
 * @return object An iterator that applies the given callback(s)
 */
function iterator_map( $it, $fn ) {
  if ( is_array( $it ) ) {
    $it = new \ArrayIterator( $it );
  }

  if ( !method_exists( $it, 'add_transform' ) ) {
    $it = new Transform( $it );
  }

  foreach ( array_slice( func_get_args(), 1 ) as $fn ) {
    $it->add_transform( $fn );
  }

  return $it;
}

/**
 * Search for file by walking up the directory tree until the first file is found or until $stop_check($dir) returns true
 * @param string|array The files (or file) to search for
 * @param string|null The directory to start searching from; defaults to CWD
 * @param callable Function which is passed the current dir each time a directory level is traversed
 * @return null|string Null if the file was not found
 */
function find_file_upward( $files, $dir = null, $stop_check = null ) {
  $files = (array) $files;
  if ( is_null( $dir ) ) {
    $dir = getcwd();
  }
  while ( is_readable( $dir ) ) {
    // Stop walking up when the supplied callable returns true being passed the $dir
    if ( is_callable( $stop_check ) && call_user_func( $stop_check, $dir ) ) {
      return null;
    }

    foreach ( $files as $file ) {
      $path = $dir . DIRECTORY_SEPARATOR . $file;
      if ( file_exists( $path ) ) {
        return $path;
      }
    }

    $parent_dir = dirname( $dir );
    if ( empty($parent_dir) || $parent_dir === $dir ) {
      break;
    }
    $dir = $parent_dir;
  }
  return null;
}

function is_path_absolute( $path ) {
  // Windows
  if ( isset($path[1]) && ':' === $path[1] )
    return true;

  return $path[0] === '/';
}

/**
 * Composes positional arguments into a command string.
 *
 * @param array
 * @return string
 */
function args_to_str( $args ) {
  return ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
}

/**
 * Composes associative arguments into a command string.
 *
 * @param array
 * @return string
 */
function assoc_args_to_str( $assoc_args ) {
  $str = '';

  foreach ( $assoc_args as $key => $value ) {
    if ( true === $value )
      $str .= " --$key";
    else
      $str .= " --$key=" . escapeshellarg( $value );
  }

  return $str;
}

/**
 * Given a template string and an arbitrary number of arguments,
 * returns the final command, with the parameters escaped.
 */
function esc_cmd( $cmd ) {
  if ( func_num_args() < 2 )
    trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );

  $args = func_get_args();

  $cmd = array_shift( $args );

  return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

/**
 * Output items in a table, JSON, CSV, ids, or the total count
 *
 * @param string        $format     Format to use: 'table', 'json', 'csv', 'ids', 'count'
 * @param array         $items      Data to output
 * @param array|string  $fields     Named fields for each item of data. Can be array or comma-separated list
 */
function format_items( $format, $items, $fields ) {
  $assoc_args = compact( 'format', 'fields' );
  $formatter = new \Terminus\Formatter( $assoc_args );
  $formatter->display_items( $items );
}

/**
 * Write data as CSV to a given file.
 *
 * @param resource $fd         File descriptor
 * @param array    $rows       Array of rows to output
 * @param array    $headers    List of CSV columns (optional)
 */
function write_csv( $fd, $rows, $headers = array() ) {
  if ( ! empty( $headers ) ) {
    fputcsv( $fd, $headers );
  }

  foreach ( $rows as $row ) {
    if ( ! empty( $headers ) ) {
      $row = pick_fields( $row, $headers );
    }

    fputcsv( $fd, array_values( $row ) );
  }
}

/**
 * Pick fields from an associative array or object.
 *
 * @param array|object Associative array or object to pick fields from
 * @param array List of fields to pick
 * @return array
 */
function pick_fields( $item, $fields ) {
  $item = (object) $item;

  $values = array();

  foreach ( $fields as $field ) {
    $values[ $field ] = isset( $item->$field ) ? $item->$field : null;
  }

  return $values;
}

/**
 * Launch system's $EDITOR to edit text
 *
 * @param  str  $content  Text to edit (eg post content)
 * @return str|bool       Edited text, if file is saved from editor
 *                        False, if no change to file
 */
function launch_editor_for_input( $input, $title = 'Terminus' ) {

  $tmpfile = wp_tempnam( $title );

  if ( !$tmpfile )
    \Terminus::error( 'Error creating temporary file.' );

  $output = '';
  file_put_contents( $tmpfile, $input );

  $editor = getenv( 'EDITOR' );
  if ( !$editor ) {
    if ( isset( $_SERVER['OS'] ) && false !== strpos( $_SERVER['OS'], 'indows' ) )
      $editor = 'notepad';
    else
      $editor = 'vi';
  }

  \Terminus::launch( "$editor " . escapeshellarg( $tmpfile ) );

  $output = file_get_contents( $tmpfile );

  unlink( $tmpfile );

  if ( $output === $input )
    return false;

  return $output;
}

/**
 * @param string MySQL host string, as defined in wp-config.php
 * @return array
 */
function mysql_host_to_cli_args( $raw_host ) {
  $assoc_args = array();

  $host_parts = explode( ':',  $raw_host );
  if ( count( $host_parts ) == 2 ) {
    list( $assoc_args['host'], $extra ) = $host_parts;
    $extra = trim( $extra );
    if ( is_numeric( $extra ) ) {
      $assoc_args['port'] = intval( $extra );
      $assoc_args['protocol'] = 'tcp';
    } else if ( $extra !== '' ) {
      $assoc_args['socket'] = $extra;
    }
  } else {
    $assoc_args['host'] = $raw_host;
  }

  return $assoc_args;
}

function run_mysql_command( $cmd, $assoc_args, $descriptors = null ) {
  if ( !$descriptors )
    $descriptors = array( STDIN, STDOUT, STDERR );

  if ( isset( $assoc_args['host'] ) ) {
    $assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
  }

  $env = (array) $_ENV;
  if ( isset( $assoc_args['pass'] ) ) {
    $env['MYSQL_PWD'] = $assoc_args['pass'];
    unset( $assoc_args['pass'] );
  }

  $final_cmd = $cmd . assoc_args_to_str( $assoc_args );

  $proc = proc_open( $final_cmd, $descriptors, $pipes, null, $env );
  if ( !$proc )
    exit(1);

  $r = proc_close( $proc );

  if ( $r ) exit( $r );
}

/**
 * Render PHP or other types of files using Mustache templates.
 *
 * IMPORTANT: Automatic HTML escaping is disabled!
 */
function mustache_render( $template_name, $data ) {
  if ( ! file_exists( $template_name ) )
    $template_name = TERMINUS_ROOT . "/templates/$template_name";

  $template = file_get_contents( $template_name );

  $m = new \Mustache_Engine( array(
    'escape' => function ( $val ) { return $val; }
  ) );

  return $m->render( $template, $data );
}

function make_progress_bar( $message, $count ) {
  if ( \cli\Shell::isPiped() )
    return new \Terminus\NoOp;

  return new \cli\progress\Bar( $message, $count );
}

function parse_url( $url ) {
  $url_parts = \parse_url( $url );

  if ( !isset( $url_parts['scheme'] ) ) {
    $url_parts = parse_url( 'http://' . $url );
  }

  return $url_parts;
}

/**
 * Check if we're running in a Windows environment (cmd.exe).
 */
function is_windows() {
  return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

/**
 * Replace magic constants in some PHP source code.
 *
 * @param string $source The PHP code to manipulate.
 * @param string $path The path to use instead of the magic constants
 */
function replace_path_consts( $source, $path ) {
  $replacements = array(
    '__FILE__' => "'$path'",
    '__DIR__'  => "'" . dirname( $path ) . "'"
  );

  $old = array_keys( $replacements );
  $new = array_values( $replacements );

  return str_replace( $old, $new, $source );
}

function is_valid_email( $email ) {
  return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

/**
 * Check if we're pointed at Hermes. Use this for conditional behavior within terminus
 * @return bool
 */
function is_hermes() {
  if( 'dashboard.getpantheon.com' === TERMINUS_HOST ) {
      return true;
  }
  return false;
}

/**
 * Check if result is an array of multiple objects or a simple array of one.
**/
function result_is_multiobj( $result ) {
  $iter = new ArrayIterator($result);
  if( is_object( $iter->current() ) OR is_array( $iter->current() ) ) {
    return true;
  }
  unset($iter);
  return false;
}

/**
 * Fetch keys from the first object in a collection
**/
function result_get_response_fields( $result ) {
  $iter = new ArrayIterator($result);
  if( !$iter ) return false;
  $keys = array_keys( (array) $iter->current() );
  $keys = array_map('ucfirst', $keys);
  unset($iter);
  return $keys;
}

/**
 * Validate Atlas UUID.
 * @param $uuid
 * @return boolean
 */
function is_valid_uuid($uuid) {
  return preg_match('#^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$#', $uuid) ? TRUE : FALSE;
}

/**
 * Get filename from a url
 * @param $url sting valid url
 */
function get_filename_from_url($url) {
  $path = parse_url($url);
  $parts = explode('/',$path['path']);
  return end($parts);
}

function sql_from_zip($filename) {
  $file = preg_replace('#\.gz$#s','', $filename);
  return $file;
}

function destination_is_valid($destination,$make=true) {
  if (file_exists($destination) AND !is_dir($destination)) {
    \Terminus::error("Destination mush be a directory. You've supplied a file.");
  }

  if (!is_dir($destination)) {
    if (!$make)
      $make = \Terminus::confirm("Directory does not exists. Create it now?");
    if ($make) {
      mkdir($destination, 0755);
    }
  }

  return $destination;
}

function handle_exception($exception) {
  $trace = $exception->getTrace();
  if (!empty($trace) AND \Terminus::get_config('verbose')) {
      foreach( $exception->getTrace() as $line ) {
        $out_line = sprintf("%s%s%s [%s:%s]", @$line['class'], @$line['type'], @$line['function'], @$line['file'], @$line['line']);
        \Terminus\Loggers\Regular::redLine(">> $out_line");
      }
  }
  //\Terminus::line(var_export($exception->getTrace(), 1));
  \Terminus::error("Exception thrown - %s", array($exception->getMessage()));
}

/**
 * Sanitize the site name field
 * @package 2.0
 */
 function sanitize_name( $str ) {
  $name = preg_replace("#[^A-Za-z0-9]#","", $str);
  $name = strtolower($name);
  return $name;
}
