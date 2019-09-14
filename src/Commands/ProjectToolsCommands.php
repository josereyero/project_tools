<?php

namespace Drush\project_tools\Commands;

use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ProjectToolsCommands extends DrushCommands implements SiteAliasManagerAwareInterface {
  use ProcessHelperCommandsTrait;

  /**
   * Run site script from drupal root passing this site's alias.
   *
   * @param string $name
   *   Script file name.
   *
   * @command project:script
   * @aliases project-script
   *
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   *
   * @return bool
   *   Command success flag.
   */
  public function script($name) {
    $drupal_root = $this->siteAliasManager()->getSelf()->root();
    $this->say("DRUPAL ROOT $drupal_root");
    return;
    $project_root = dirname($drupal_root);

    foreach (['', '.sh', '.py'] as $extension) {
      $script = $project_root . '/scripts/' . $name . $extension;
      if (file_exists($script)) {
        $this->runShellCommand($script, $drupal_root);
        return;
      }
    }
    throw new \Exception(sprintf("Script %s not found.", $script));
  }

  /**
   * Reload target site from source site.
   *
   *
   * @command prject:reload
   * @aliases project-reload
   */
  public function reload() {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
    throw new \Exception("Command not implemented");
  }

}
