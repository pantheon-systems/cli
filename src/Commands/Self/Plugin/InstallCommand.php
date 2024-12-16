<?php

namespace Pantheon\Terminus\Commands\Self\Plugin;

use Consolidation\AnnotatedCommand\CommandData;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;
use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class InstallCommand.
 *
 * Installs a Terminus plugin using Composer.
 *
 * @package Pantheon\Terminus\Commands\Self\Plugin
 */
class InstallCommand extends PluginBaseCommand
{
    public const ALREADY_INSTALLED_MESSAGE = '{project} is already installed.';

    public const INVALID_PROJECT_MESSAGE = '{project} is not a valid Packagist project.';

    public const USAGE_MESSAGE = 'terminus self:plugin:<install|add> <Packagist project 1> [Packagist project 2] ...';

    /**
     * Install one or more Terminus plugins.
     *
     * @command self:plugin:install
     * @aliases self:plugin:add plugin:install plugin:add
     *
     * @param array $projects
     *   A list of one or more plugin projects to install. Projects may include
     *     version constraints.
     *
     * @usage <project 1> [project 2] ...
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function install(array $projects)
    {
        $projects = $this->convertRepositoryProjects($projects);
        foreach ($projects as $projectName => $installationPath) {
            if ($this->validateProject($projectName, $installationPath)) {
                $this->log()->info(sprintf('Installing %s...', $projectName));
                $results = $this->doInstallation(
                    $projectName,
                    $installationPath
                );
                $this->log()->notice($results['output']);
            }
        }
    }

    /**
     * Migrate Terminus 2 plugins.
     *
     * @command self:plugin:migrate
     * @aliases plugin:migrate
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function migrate()
    {
        $plugins_dir = $this->getConfig()->get('plugins2_dir');
        if (!is_dir($plugins_dir)) {
            $this->log()->notice('No Terminus 2 plugins to migrate.');
            return;
        }

        $plugin_dirs = glob(
            $plugins_dir . DIRECTORY_SEPARATOR . '*',
            GLOB_ONLYDIR
        );
        if (!$plugin_dirs) {
            $this->log()->notice('No Terminus 2 plugins to migrate.');
            return;
        }

        // Get installed Terminus 4 plugins.
        $plugins = $this->getPluginProjects();
        $t3projects = array_filter(
            array_map(
                fn ($plugin) => $plugin->getName(),
                $plugins
            )
        );

        // Get installed Terminus 2 plugins.
        $t2projects = array_filter(
            array_map(
                fn ($dir) => $this->getProjectNameFromPath($dir),
                $plugin_dirs
            )
        );

        // Get only the Terminus 2 plugins that need migrated.
        $projects = array_diff($t2projects, $t3projects);

        if (empty($projects)) {
            $this->log()->notice('No Terminus 2 plugins to migrate.');
            return;
        }

        $this->log()->notice('Migrating Terminus 2 plugins...');
        $this->install($projects);
        $this->log()->notice('Successfully migrated all Terminus 2 plugins.');
    }

    /**
     * Check for minimum plugin command requirements.
     *
     * @hook validate self:plugin:install
     *
     * @param CommandData $commandData
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function validate(CommandData $commandData)
    {
        $this->checkRequirements();

        if (empty($commandData->input()->getArgument('projects'))) {
            throw new TerminusNotFoundException(self::USAGE_MESSAGE);
        }
    }

    /**
     * Convert given projects into an array indexed by project name and path
     * (if exists) as value.
     *
     * @param array $projects
     *
     * @return array
     *  - key is project name;
     *  - value is path toa local installation (if exists). Otherwise - NULL.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function convertRepositoryProjects(array $projects): array
    {
        $convertedProjects = [];

        foreach ($projects as $projectNameOrPath) {
            if ($this->isGitRepo($projectNameOrPath)) {
                $pluginsDir = $this->getConfig()->get('plugins_dir');
                $folderName = basename($projectNameOrPath);
                $pluginFolderName = sprintf('%s/%s', $pluginsDir, $folderName);
                $fs = $this->getLocalMachine()->getFileSystem();
                if (is_dir($pluginFolderName)) {
                    // If folder exists, try removing it, and if it fails, throw an error.
                    $fs->remove($pluginFolderName);
                }
                $fs->mkdir($pluginFolderName);
                $command = sprintf(
                    'git -C %s clone %s --depth 1 .',
                    $pluginFolderName,
                    $projectNameOrPath
                );
                $results = $this->runCommand($command);
                if ($results['exit_code'] !== 0) {
                    throw new TerminusException(
                        'Error cloning repo {repo} into path {path}.',
                        [
                            'repo' => $projectNameOrPath,
                            'path' => $pluginFolderName,
                        ]
                    );
                }
                $projectNameOrPath = $pluginFolderName;
            }
            if ($this->hasProjectAtPath($projectNameOrPath)) {
                $projectName = $this->getProjectNameFromPath(
                    $projectNameOrPath
                );
                // A project name was found at the path, so record the name and its path.
                $convertedProjects[$this->getComposerProjectName(
                    $projectName
                )] = $projectNameOrPath;
            } else {
                // Presume the parameter is a packagist project.
                $convertedProjects[$this->getComposerProjectName(
                    $projectNameOrPath
                )] = null;
            }
        }

        return $convertedProjects;
    }

    /**
     * Determines if given url is a git repo or not.
     */
    protected function isGitRepo($possibleUrl)
    {
        return preg_match(
            '/^(git@|https:\/\/|git:\/\/).*\.git$/',
            $possibleUrl
        );
    }

    /**
     * Determines whether the given path contains a composer project.
     */
    protected function hasProjectAtPath($projectNameOrPath): bool
    {
        // If the specified path does not exist or does not have a composer.json file, presume it is a project.
        $composerJson = $projectNameOrPath . DIRECTORY_SEPARATOR . 'composer.json';

        return is_dir($projectNameOrPath) && file_exists($composerJson);
    }

    /**
     * Installs the plugin.
     *
     * @param string $projectName
     *   Name of project to be installed.
     * @param string|null $installationPath
     *   If not NULL, install as a path repository.
     *
     * @return array
     *   Results from the "install" command.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function doInstallation(
        string $projectName,
        ?string $installationPath
    ) {
        return $this->installProject($projectName, $installationPath);
    }

    /**
     * Validate given project is valid.
     *
     * @param string $projectName
     * @param string|null $installationPath
     *
     * @return bool
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function validateProject(
        string $projectName,
        ?string $installationPath
    ): bool {
        if (
            null === $installationPath && !$this->isPackagistProject(
                $projectName
            )
        ) {
            $this->log()->error(
                self::INVALID_PROJECT_MESSAGE,
                ['project' => $projectName,]
            );
            return false;
        }

        if ($this->isInstalled($projectName)) {
            $this->log()->notice(
                self::ALREADY_INSTALLED_MESSAGE,
                ['project' => $projectName,]
            );
            return false;
        }

        return true;
    }

    /**
     * Returns the project name.
     *
     * @param string $project
     *
     * @return string
     */
    private function getComposerProjectName(string $project)
    {
        $parts = explode('/', $project);
        if (1 === count($parts)) {
            return sprintf('pantheon-systems/%s', $project);
        }

        return $project;
    }
}
