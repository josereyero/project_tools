<?php

namespace Drush\Commands\project_tools;

use Drush\Commands\DrushCommands;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Reyero\DrupalTools\Drush\ProcessHelperCommandsTrait;
use Reyero\DrupalTools\Drush\ProjectInfoCommandsTrait;
use Reyero\DrupalTools\ProjectTools;

/**
 * Project Tools global drush commands.
 *
 * @todo Command aliases not working for some reason.
 */
class ProjectToolsCommands extends DrushCommands implements SiteAliasManagerAwareInterface {
  use SiteAliasManagerAwareTrait;
  use ProcessHelperCommandsTrait, ProjectInfoCommandsTrait;

  /**
   * The project settings
   *
   * @var \Reyero\DrupalTools\ProjectTools\ProjectSettings
   */
  protected $project_settings;

  /**
   * List all projects / environments.
   *
   * @command project:list
   * @alias project-list
   */
  public function projectList() {
    foreach ($this->getProjectSettings()->getProjects() as $project_name => $project_info) {
      $this->output()->writeln(sprintf("%s %s", $project_name, $project_info['project_label']));
      foreach ($this->getProjectSettings()->getProjectEnvironments($project_name) as $env_name => $env_data) {
        $this->output()->writeln(sprintf("- %s %s %s", $env_name, $env_data['site_alias'], $env_data['base_url']));
      }
    }
  }

  /**
   * List site status for all projects / environments.
   *
   * @command project:status
   * @alias project-status
   */
  public function projectStatus($project_filter = NULL, $env_filter = NULL) {
    foreach ($this->getProjectSettings()->getProjectEnvironments() as $project_name => $project_envs) {
      // Filter out other projects if project filter set.
      if ($project_filter && $project_name != $project_filter) continue;

      foreach ($project_envs as $env_name => $environment) {
        // Filter out other environments if env filter set.
        if ($env_filter && $env_name != $env_filter) continue;

        $site_alias = $environment['site_alias'];

        $variables = ['@project'=> $project_name, '@environment' => $env_name, '@alias' => $site_alias];
        if ($site = $this->siteAliasManager()->get($site_alias)) {
          $this->printMessage("Checking site status for @project/@environment: @alias", $variables);
          $this->siteDrushCommand($site, 'core-status');
        }
        else {
          $this->logger()->warning("Site alias not found for @project/@environment: @alias", $variables);
        }
      }
    }
  }

  /**
   * Run site script from drupal root passing this site's alias.
   *
   * @param string $name
   *   Script file name.
   *
   * @command project:script
   * @aliases project-script
   *
   * @bootstrap none
   *
   * @return bool
   *   Command success flag.
   */
  public function projectScript($name) {
    $scripts_path = $this->getScriptsPath();
    foreach (['', '.sh', '.py'] as $extension) {
      $script = $scripts_path . '/' . $name . $extension;
      if (file_exists($script)) {
        return $this->runShellCommand($script, $this->getDrupalRoot());
      }
    }
    throw new \Exception(sprintf("Script %s not found.", $script));
  }


  /**
   * Reload target site from source site.
   *
   *
   * @command project:reload
   * @aliases project-reload
   */
  public function reload() {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
    throw new \Exception("Command not implemented");
  }

  /**
   * Get Project Settings.
   *
   * @return \Reyero\DrupalTools\ProjectTools\ProjectSettings
   */
  protected function getProjectSettings() {
    if (!isset($this->project_settings)) {
      $this->project_settings = ProjectTools::initFromDrupalRoot($this->getDrupalRoot());
    }
    return $this->project_settings;
  }
}
