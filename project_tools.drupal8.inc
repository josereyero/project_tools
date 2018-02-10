<?php

/**
 * Project Tools library - Drupal 8
 */

/**
 * Enable / disable maintenance mode.
 *
 * @param string $site_alias
 *
 * @param boolean $maintenance
 *   TRUE to set maintenance mode, FALSE to unset.
 */
function project_tools_set_maintenance($site_alias, $maintenance) {
  $value = $maintenance ? '1' : '0';
  $response = drush_invoke_process($site_alias, 'state-set', ['system.maintenance_mode', $value]);
}
