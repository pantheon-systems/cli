<?php

// This makes Robo commands available from inside a PHPUnit Test
if (!class_exists("\RoboFile")) {
    require_once('RoboFile.php');
};

/**
 * Bootstrap file for functional tests.
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pantheon\Terminus\Tests\Functional\TerminusTestBase;

// create a log channel
global $log;


$log =& $GLOBALS['LOGGER'];
$log = new Logger('PHPUNIT');
$tokens_dir = implode(DIRECTORY_SEPARATOR, [$_SERVER['HOME'], '.terminus', 'cache', 'tokens']);
if (!is_dir($tokens_dir)) {
    mkdir(
        $tokens_dir,
        0700,
        true
    );
}

$testcache_dir = implode(DIRECTORY_SEPARATOR, [$_SERVER['HOME'], '.terminus', 'testcache']);
if (!is_dir($testcache_dir)) {
    mkdir(
        $testcache_dir,
        0700,
        true
    );
}
$log->pushHandler(new StreamHandler($testcache_dir . "/phpunit_tests.log", Logger::DEBUG));
$log->info(print_r($GLOBALS, true));
// Override the default cache directory by setting an environment variable. This prevents our tests from overwriting
// the user's real cache and session.
if (!getenv('TERMINUS_CACHE_DIR')) {
    // Set the terminus cache directory if not already set
    putenv(sprintf('TERMINUS_CACHE_DIR=%s/.terminus/testcache', getenv('HOME')));
}
$cache_dir = getenv('TERMINUS_CACHE_DIR');
if (!is_dir($cache_dir)) {
    mkdir(
        $cache_dir,
        0700,
        true
    );
}

if (!getenv('TERMINUS_ALLOW_UNSUPPORTED_NEWER_PHP')) {
    // Terminus allow unsupported version.
    putenv('TERMINUS_ALLOW_UNSUPPORTED_NEWER_PHP=1');
}

// If the bin file doesn't exist, build it.
define("TERMINUS_BIN_FILE", realpath('./terminus.phar'));
$version = exec(sprintf('%s --version', TERMINUS_BIN_FILE));
if (!file_exists(TERMINUS_BIN_FILE)) {
    exec(
        'composer pre-commit && composer phar:build && composer install --dev'
    );
    if (!file_exists(TERMINUS_BIN_FILE)) {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new Exception('Could not build Terminus PHAR file.');
    }
}
$log->info('Using Terminus PHAR file: %s VERSION: %s', [TERMINUS_BIN_FILE, $version]);
chmod(TERMINUS_BIN_FILE, 0700);


// if there is no pre-existing session, grab the machine token
// and use it to login to create a session
if (!file_exists($cache_dir . '/session')) {
    $token = getenv('TERMINUS_TOKEN');
    if (empty($token)) {
        $dir = new DirectoryIterator($tokens_dir);
        $tokens = array_diff(
            scandir(
                $dir->getRealPath(),
                SCANDIR_SORT_DESCENDING
            ),
            ['..', '.']
        );
        if (count($tokens)) {
            $token = array_shift($tokens);
            $tokenData = json_decode(
                file_get_contents(
                    $dir->getRealPath() . DIRECTORY_SEPARATOR . $token
                ),
                false,
                JSON_THROW_ON_ERROR
            );
            putenv(sprintf('TERMINUS_TOKEN=%s', $tokenData->token));
        }
    }
    exec(
        sprintf(
            '%s auth:login --machine-token=%s',
            TERMINUS_BIN_FILE,
            $token
        )
    );
}


if (!getenv('TERMINUS_TESTING_RUNTIME_ENV')) {
    // Create a testing runtime multidev environment.
    $sitename = TerminusTestBase::getSiteName();

    $multidev = sprintf('test-%s', substr(uniqid(), -6, 6));
    $createMdCommand = sprintf('multidev:create %s.dev %s', $sitename, $multidev);

    exec(
        sprintf('%s %s', TERMINUS_BIN_FILE, $createMdCommand),
        $output,
        $code
    );
    if (0 !== $code) {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new Exception(
            sprintf(
                'Command "%s" exited with non-zero code (%d). Output: %s',
                $createMdCommand,
                $code,
                implode("\n", $output)
            )
        );
    }

    TerminusTestBase::setMdEnv($multidev);

    register_shutdown_function(function () use ($sitename, $multidev) {
        // Delete a testing runtime multidev environment.
        $deleteMdCommand = sprintf('multidev:delete %s.%s --delete-branch --yes', $sitename, $multidev);
        exec(
            sprintf('%s %s', TERMINUS_BIN_FILE, $deleteMdCommand),
            $output,
            $code
        );

        if (0 !== $code) {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw new Exception(sprintf('Command "%s" exited with non-zero code (%d)', $deleteMdCommand, $code));
        }
    });
}
