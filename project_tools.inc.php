<?php

/**
 * Drupal / Drush: project_tools library.
 *
 * Configuration must be included in drushrc.php
 *
 */

/**
 * Gets project settings configuration for project tools.
 *
 * @param string $name
 *   Project name.
 * @return array
 *   Project environments.
 *
 * @throws \Exception
 */
function project_tools_get_project_settings($name = NULL) {
  $projects = drush_get_option('project_settings');

  if (!isset($name)) {
    return $projects;
  }
  elseif (isset($projects[$name])) {
    return $projects[$name];
  }
  else {
    throw new \Exception(sprintf("Project not found: %s", $name));
  }
}

/**
 * Gets environment information for project / environment.
 *
 * @return array
 *
 * @throws \Exception
 */
function project_tools_get_environment($project_name, $env_name) {
  $project = project_tools_get_project_environments($project_name);
  if ($project && isset($project[$env_name])) {
    return $project[$env_name] + project_tools_get_project_settings($project_name);
  }
  else {
    throw new \Exception(sprintf("Environment %s not found for project %s", $env_name, $project_name));
  }
}

/**
 * Gets environment configuration for project tools.
 *
 * @param string $name
 *   Project name.
 * @return array
 *   Project environments.
 *
 * @throws \Exception
 */
function project_tools_get_project_environments($name = NULL) {
  $environments = drush_get_option('project_environments');

  if (!isset($name)) {
    return $environments;
  }
  elseif (isset($environments[$name])) {
    return $environments[$name];
  }
  else {
    throw new \Exception(sprintf("Project not found: %s", $name));
  }
}

/**
 * Gets default project name.
 *
 * Useful when there's a single project, in other cases will just
 * return the first one.
 *
 * @return string
 *   The default project name.
 */
function project_tools_get_default_project() {
  $projects = project_tools_get_project_settings();
  return key($projects);
}

/**
 * Run ansible playbook for project/env.
 *
 * @param string $project_name
 *   Project name.
 * @param string $env_name
 *   Environment name.
 * @param string $playbook_name
 *   Playbook name as defined in project settings.
 */
function project_tools_ansible_playbook($project_name, $target_env, $playbook_name) {
  $environment = project_tools_get_environment($project_name, $target_env);

  $variables = [
      '@project' => $project_name,
      '@environment' => $target_env,
      '@playbook_name' => $playbook_name,
  ];

  if (!isset($environment['ansible_playbooks'][$playbook_name])) {
    return drush_set_error(dt("Cannot find ansible playbook @playbook_name for project/env @project/@environment", $variables));
  }

  $playbook = $environment['ansible_playbooks'][$playbook_name];
  $inventory = $environment['ansible_inventory'];
  $extra_vars = [
    'project_name' => $project_name,
    'project_env' => $target_env,
    'drush_alias' => $environment['site_alias'],
    'drupal_root' => $environment['drupal_root'],
  ];
  // Ansible paths.
  $ansible_path = $environment['ansible_directory'];
  $playbook_path = $ansible_path . '/' . $playbook;
  $inventory_path = $ansible_path . '/' . $inventory;

  $variables['@target'] = $environment['site_alias'];
  $variables['@playbook'] = $playbook_path;
  $variables['@inventory'] = $inventory_path;

  drush_print(dt("Run @playbook_name for site @target", $variables));
  drush_print(dt("Ansible playbook: @playbook", $variables), 2);
  drush_print(dt("Andible inventory: @inventory", $variables), 2);
  if (!drush_confirm(dt("Continue?", $variables))) {
    return drush_set_error("Cancelled!!!");
  }

  // Prepare extra vars
  $extra = array();
  foreach ($extra_vars as $name => $value) {
    $extra[] = "$name=$value";
  }
  $extra_str = implode(' ', $extra);

  drush_shell_exec_interactive('ansible-playbook -i %s %s -v --extra-vars %s', $inventory_path, $playbook_path, $extra_str);
}

/**
 * Check results.
 *
 * @param mixed $result
 *   Drush command result or drush_invoke_process() return value.
 *
 * @return boolean
 *   TRUE if not errors.
 */
function _drush_project_tools_check_result($result) {
  if ($result === FALSE || (is_array($result) && !empty($result['error_status']))) {
    return FALSE;
  }
  else  {
    return TRUE;
  }
}

/**
 * Run multiple drush commands through drush invoke process.
 *
 * @param $site_alias
 *   Alias of the site to run commands on.
 * @param array $commands
 *   Array of command name => arguments
 * @param array $options
 *   Mixed options as option_name => option_value
 *   - 'maintenance_mode' = TRUE to set maintenance mode during operation.
 *   - 'interactive' = TRUE to run in interactive mode.
 */
function _drush_project_tools_run_commands($site_alias, array $commands, $options = array()) {
  $options += [
      'maintenance_mode' => FALSE,
      'interactive' => FALSE,
  ];

  $return = TRUE;

  if ($options['maintenance_mode']) {
    $maintenance_mode = _drush_project_tools_set_maintenance($site_alias, TRUE);
  }

  $commandline_options = $backend_options = array();

  $backend_options['interactive'] = $options['interactive'];

  foreach ($commands as $command) {
    $command = array_merge($command, [[], []]);
    list($command_name, $arguments, $commandline_options) = $command;

    $result = drush_invoke_process($site_alias, $command_name, $arguments, $commandline_options);
    if ($result === FALSE || !empty($result['error_status'])) {
      drush_set_error(dt("Error running drush command: @command", ['@command' => $command_name]));
      $return = FALSE;
      break;
    }
  }

  if ($options['maintenance_mode']) {
    _drush_project_tools_set_maintenance($site_alias, FALSE);
  }

  return $return;
}

/**
 * Set site maintenance mode on / off.
 *
 * @return boolean
 *   TRUE if successful.
 */
function _drush_project_tools_set_maintenance($site_alias, $value) {
  $value = $value ? '1' : '0';
  // This drush command is defined by site_tools for both D7 and D8.
  $result = drush_invoke_process($site_alias, 'site-set-maintenance-mode', [$value]);
  return _drush_project_tools_check_result($result);
}

/**
 * Invoke project-update for single project.
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $env_name
 *   Environment name.
 */
function _drush_project_tools_project_update($project_name, $env_name) {

  $environment = project_tools_get_environment($project_name, $env_name);

  $site_alias = $environment['site_alias'];

  // Enable site_tools module just in case.
  $commands = array();
  $commands[] = ['pm-enable', ['site_tools']];
  $commands[] = ['site-update'];

  // Now call site-update script.
  $options = [
      'interactive' => TRUE,
      'maintenance_mode' => TRUE,
  ];

  return _drush_project_tools_run_commands($site_alias, $commands, $options);
}


