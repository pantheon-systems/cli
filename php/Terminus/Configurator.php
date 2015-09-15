<?php

namespace Terminus;

class Configurator {
  private static $special_flags = array('--no-cache-clear');
  private $config = array();
  private $extra_config = array();
  private $spec;


  function __construct($path) {
    $this->spec = include $path;

    $defaults = array(
      'runtime' => false,
      'file' => false,
      'synopsis' => '',
      'default' => null,
      'multiple' => false,
   );

    foreach ($this->spec as $key => &$details) {
      $details = array_merge($defaults, $details);

      $this->config[$key] = $details['default'];
    }
  }

  function to_array() {
    return array($this->config, $this->extra_config);
  }

  /**
   * Get configuration specification, i.e. list of accepted keys.
   *
   * @return array
   */
  function get_spec() {
    return $this->spec;
  }

  /**
   * Splits a list of arguments into positional, associative and config.
   *
   * @param array(string)
   * @return array(array)
   */
  public function parse_args($arguments) {
    list($positional_args, $mixed_args) = self::extract_assoc($arguments);
    list($assoc_args, $runtime_config) = $this->unmix_assoc_args($mixed_args);
    return array($positional_args, $assoc_args, $runtime_config);
  }

  /**
   * Splits positional args from associative args.
   *
   * @param array
   * @return array(array)
   */
  public static function extract_assoc($arguments) {
    $positional_args = $assoc_args = array();

    foreach ($arguments as $arg) {
      if (in_array($arg, self::$special_flags)) {
        $assoc_args[] = array(str_replace('--', '', $arg), null);
      } elseif (preg_match('|^--no-([^=]+)$|', $arg, $matches)) {
        $assoc_args[] = array($matches[1], false);
      } elseif (preg_match('|^--([^=]+)$|', $arg, $matches)) {
        $assoc_args[] = array($matches[1], true);
      } elseif (preg_match('|^--([^=]+)=(.+)|s', $arg, $matches)) {
        $assoc_args[] = array($matches[1], $matches[2]);
      } else {
        $positional_args[] = $arg;
      }
    }

    return array($positional_args, $assoc_args);
  }

  private function unmix_assoc_args($mixed_args) {
    $assoc_args = $runtime_config = array();

    foreach ($mixed_args as $tmp) {
      list($key, $value) = $tmp;

      if (isset($this->spec[$key]) && $this->spec[$key]['runtime'] !== false) {
        $details = $this->spec[$key];

        if (isset($details['deprecated'])) {
          fwrite(STDERR, "Terminus: The --{$key} global parameter is deprecated. {$details['deprecated']}\n");
        }

        if ($details['multiple']) {
          $runtime_config[$key][] = $value;
        } else {
          $runtime_config[$key] = $value;
        }
      } else {
        $assoc_args[$key] = $value;
      }
    }

    return array($assoc_args, $runtime_config);
  }

  function merge_yml($path) {
    foreach (self::load_yml($path) as $key => $value) {
      if (!isset($this->spec[$key]) || false === $this->spec[$key]['file']) {
        $this->extra_config[$key] = $value;
      } elseif ($this->spec[$key]['multiple']) {
        self::arrayify($value);
        $this->config[$key] = array_merge($this->config[$key], $value);
      } else {
        $this->config[$key] = $value;
      }
    }
  }

  function merge_array($config) {
    foreach ($this->spec as $key => $details) {
      if (false !== $details['runtime'] && isset($config[$key])) {
        $value = $config[$key];

        if ($details['multiple']) {
          self::arrayify($value);
          $this->config[$key] = array_merge($this->config[$key], $value);
        } else {
          $this->config[$key] = $value;
        }
      }
    }
  }

  /**
   * Load values from a YAML file.
   */
  private static function load_yml($yml_file) {
    if (!$yml_file)
      return array();

    $config = spyc_load_file($yml_file);

    // Make sure config-file-relative paths are made absolute.
    $yml_file_dir = dirname($yml_file);

    if (isset($config['path']))
      self::absolutize($config['path'], $yml_file_dir);

    if (isset($config['require'])) {
      self::arrayify($config['require']);
      foreach ($config['require'] as &$path) {
        self::absolutize($path, $yml_file_dir);
      }
    }

    return $config;
  }

  private static function arrayify(&$val) {
    if (!is_array($val)) {
      $val = array($val);
    }
  }

  private static function absolutize(&$path, $base) {
    if (!empty($path) && !\Terminus\Utils\is_path_absolute($path)) {
      $path = $base . DIRECTORY_SEPARATOR . $path;
    }
  }
}

