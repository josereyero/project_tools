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

  drush_shell_exec_interactive('ansible-playbook -i %s %s -v', $inventory_path, $playbook_path);
}


