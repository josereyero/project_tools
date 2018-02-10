<?php

/**
 * Project Tools library - Drupal 7
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
  $response = drush_invoke_process($site_alias, 'variable-set', ['maintenance_mode', $value]);
}
