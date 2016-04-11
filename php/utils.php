<?php

namespace Terminus\Utils;

/**
 * Terminus is in test mode
 *
 * @return bool
 */
function isTest() {
  $is_test = (
    (boolean)getenv('CLI_TEST_MODE')
    || (boolean)getenv('VCR_CASSETTE')
  );
  if ((boolean)getenv('TERMINUS_TEST_IGNORE')) {
    $is_test = !$is_test;
  }
  return $is_test;
}

/**
 * Check whether Terminus is running in a certain OS environment
 *
 * @return bool True if OS running Terminus is based on argument passed
 */
function isOS($test_os = '') {
  $test_os = strtoupper($test_os);
  $os = strtoupper(substr(PHP_OS, 0, 3));
  switch ($test_os) {
    case 'MAC':
      $is_os = ($os == 'DAR');
      break;
    case 'LINUX':
      $is_os = ($os == 'LIN');
      break;
    case 'WINDOWS':
      $is_os = ($os == 'WIN');
      break;
    default:
      $is_os = false;
  }
  if ((boolean)getenv('TERMINUS_TEST_IGNORE')) {
    $is_os = !$is_os;
  }
  return $is_os;
}

/**
 * Check whether Terminus is running in a Mac environment
 *
 * @return bool True if OS running Terminus is Mac
 */
function isMac() {
  $is_mac = strtoupper(substr(PHP_OS, 0, 3)) == 'DAR';
  if ((boolean)getenv('TERMINUS_TEST_IGNORE')) {
    $is_mac = !$is_mac;
  }
  return $is_mac;
}

/**
 * Check whether Terminus is running in a Linux environment
 *
 * @return bool True if OS running Terminus is Linux
 */
function isLinux() {
  $is_linux = strtoupper(substr(PHP_OS, 0, 3)) == 'LIN';
  if ((boolean)getenv('TERMINUS_TEST_IGNORE')) {
    $is_linux = !$is_linux;
  }
  return $is_linux;
}

/**
 * Check whether Terminus is running in a Windows environment
 *
 * @return bool True if OS running Terminus is Windows
 */
function isWindows() {
  $is_windows = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
  if ((boolean)getenv('TERMINUS_TEST_IGNORE')) {
    $is_windows = !$is_windows;
  }
  return $is_windows;
}
