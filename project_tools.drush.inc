<?php

/**
 * @file
 *
 * Drush project tools - Reyero.net
 *
 * Provides some tool to manage projects / environments defined in drushrc.php
 *
 * Define projects and directories:
 *
 * $project_base_dir = "/var/www/myproject";
 *
 * $options['project_directories'] = [
 *   'repository' => $project_base_dir,
 *   'ansible' => "$project_base_dir/ansible",
 *   'scripts' = "$project_base_dir/scripts",
 *   'backups' = "$project_base_dir/backups",
 * ];
 *
 * $options['project_environments']['myproject']['production'] =
 *   'site_alias' => '@mysite-prod',
 *   'ansible_inventory' => 'hosts/production',
 *   'deployment_strategy' => 'ansible',
 *   'backup_directory' => '/var/backups/myproject',
 * ];
 *
 */

/**
 * Implements hook_drush_command().
 */
function project_tools_drush_command() {

  $items['project-status'] = [
    'description' => 'Check site status for all projects / environments',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  $items['project-update'] = [
    'description' => 'Update project site, enable master and environment modules, revert features',
    'arguments' => [
      'projec_name' => 'Project name',
      'env_name' => 'Environment name (devel, testing, production, etc...)',
    ],
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  $items['project-deploy'] = [
      'description' => 'Update project site, enable master and environment modules, revert features',
      'arguments' => [
          'projec_name' => 'Project name',
          'env_name' => 'Environment name (devel, testing, production, etc...)',
      ],
      'options' => [
        'skip-backup' => 'Skip database backup',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  $items['project-reload'] = [
      'description' => 'Reload site database and run site update scripts',
      'arguments' => [
          'projec_name' => 'Project name',
          'target_env' => 'Target environment.',
          'source_env' => 'Source environment.'
      ],
      'options' => [
          'skip-backup' => 'Skip database backup',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  $items['project-reload-and-update'] = [
      'description' => 'Reload site database and run site update scripts',
  ] + $items['project-reload'];

  $items['project-backup'] = [
      'description' => 'Reload site database and run site update scripts',
      'arguments' => [
          'projec_name' => 'Project name',
          'env_name' => 'Environment name (devel, testing, production, etc...)',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  $items['project-checkout'] = [
      'description' => 'Checkout project code, do not run scripts.',
      'arguments' => [
          'projec_name' => 'Project name',
          'env_name' => 'Environment name (devel, testing, production, etc...)',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  ];

  return $items;
}

/**
 * Drush command callback: project-status
 *
 * List site status for all projects / environments.
 */
function drush_project_tools_project_status($project_filter = NULL, $env_filter = NULL) {
  _drush_project_tools_include();

  foreach (project_tools_get_project_environments() as $project_name => $project_envs) {
    // Filter out other projects if project filter set.
    if ($project_filter && $project_name != $project_filter) continue;

    // If this is a group of projects, skip status
    $project_settings = project_tools_get_project_settings($project_name);
    if (!empty($project_settings['group_projects'])) {
      drush_print(dt("Skipping group project: @project (@group)", ['@project'=> $project_name, '@group' => implode(', ', $project_settings['group_projects'])]));
      continue;
    }

    foreach ($project_envs as $env_name => $environment) {
      // Filter out other environments if env filter set.
      if ($env_filter && $env_name != $env_filter) continue;

      $site_alias = $environment['site_alias'];
      drush_print(dt("Checking site status for @project/@environment: @alias", ['@project'=> $project_name, '@environment' => $env_name, '@alias' => $site_alias]));
      drush_print();
      drush_invoke_process($site_alias, 'core-status');
    }
  }
}

/**
 * Drush command callback: project-update
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $env_name
 *   Environment name.
 */
function drush_project_tools_project_update($project_name, $env_name) {
  _drush_project_tools_include();

  $project_name = $project_name ?: project_tools_get_default_project();
  $project_settings = project_tools_get_project_settings($project_name);

  drush_print(dt('Updating project environment: @project/@environment.', ['@project' => $project_name, '@environment' => $env_name]));
  $result = _drush_project_tools_project_update($project_name, $env_name);
}

/**
 * Drush command callback: project-deploy
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $env_name
 *   Environment name.
 */
function drush_project_tools_project_deploy($project_name, $target_env) {
  _drush_project_tools_include();

  $project_name = $project_name ?: project_tools_get_default_project();
  $environment = project_tools_get_environment($project_name, $target_env);

  $variables = [
    '@project' => $project_name,
    '@environment' => $target_env,
  ];

  // Skip backup is the target is not 'devel'
  $skip_backup = drush_get_option('skip-backup', $target_env == 'devel');

  if (!$skip_backup) {
    drush_project_tools_project_backup($project_name, $target_env);
  }

  $strategy = $environment['deployment_strategy'];

  switch ($strategy) {
    case 'ansible':
      drush_print(dt("Deploying @project/@environment using Ansible.", $variables));
      drush_print();
      project_tools_ansible_playbook($project_name, $target_env, 'deploy');
      break;
    default:
      return drush_set_error(dt("Deployment strategy not found for @project/@environment.", $variables));

  }

  drush_print();
  drush_print("Deployment finished.");
}

/**
 * Drush command callback: project-deploy
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $env_name
 *   Environment name.
 */
function drush_project_tools_project_checkout($project_name, $target_env) {
  _drush_project_tools_include();

  $project_name = $project_name ?: project_tools_get_default_project();
  $environment = project_tools_get_environment($project_name, $target_env);

  $variables = [
      '@project' => $project_name,
      '@environment' => $target_env,
  ];

  $strategy = $environment['deployment_strategy'];

  switch ($strategy) {
    case 'ansible':
      drush_print(dt("Checking out @project/@environment using Ansible.", $variables));
      drush_print();
      project_tools_ansible_playbook($project_name, $target_env, 'checkout');
      break;
    default:
      return drush_set_error(dt("Deployment strategy not found for @project/@environment.", $variables));

  }

  drush_print();
  drush_print("Checkout finished.");
}

/**
 * Drush command callback: project-reload
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $target_env
 *   Target environment name, the one to reload into.
 * @param string $source_env
 *   Source environment, to reload from.
 */
function drush_project_tools_project_reload($project_name, $target_env, $source_env = NULL) {
  _drush_project_tools_include();

  // Do not reload production site.
  if ($target_env == 'production') {
    return drush_set_error("Nope, we don't want to reload PRODUCTION environment.");
  }

  $project_name = $project_name ?: project_tools_get_default_project();

  $target_settings = project_tools_get_environment($project_name, $target_env);

  $source_env = $source_env ?: $target_settings['reload_source'];

  $source_settings = project_tools_get_environment($project_name, $source_env);
  $target_site = $target_settings['site_alias'];
  $source_site = $source_settings['site_alias'];

  $variables = [
    '@project' => $project_name,
    '@source' => $source_env,
    '@target' => $target_env,
    '@source_site' => $source_site,
    '@target_site' => $target_site,
  ];

  drush_print(dt("Reload and update @project/@target: @target_site,", $variables));
  drush_print(dt("from @project/@source: @source_site", $variables), 2);

  if (!drush_confirm(dt("This will drop and replace @target_site database. Continue?", $variables))) {
    return drush_set_error("Cancelled!!!");
  }

  // Skip backup if the target is not 'devel'
  $skip_backup = drush_get_option('skip-backup', $target_env == 'devel');

  if (!$skip_backup) {
    drush_project_tools_project_backup($project_name, $target_env);
  }

  // For extra safety, make it interactive.
  $backend_options = [
      'interactive' => TRUE,
  ];

  $commandline_options = array();

  $result = drush_invoke_process($target_site, "sql-drop", array(), $commandline_options, $backend_options);

  if ($result === FALSE || !empty($result['error_status'])) {
    return drush_set_error("Cancelled!!!");
  }

  $commandline_options = ['--structure-tables-key=common'];

  $result = drush_invoke_process('@self', "sql-sync", [$source_site, $target_site], $commandline_options, $backend_options);

  if ($result === FALSE || !empty($result['error_status'])) {
    return drush_set_error("Cancelled!!!");
  }

}

/**
 * Drush command callback: project-reload-and-update
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $target_env
 *   Target environment name, the one to reload into.
 * @param string $source_env
 *   Source environment, to reload from.
 */
function drush_project_tools_project_reload_and_update($project_name, $target_env, $source_env = NULL) {
  $result = drush_project_tools_project_reload($project_name, $target_env, $source_env);
  if (!_drush_project_tools_check_result($result)) {
    return drush_set_error("Reload failed, cancelled site update!!!");
  }
  $result = drush_project_tools_project_update($project_name, $target_env);
  if (!_drush_project_tools_check_result($result)) {
    return drush_set_error("Failed site update!!!");
  }
}

/**
 * Drush command callback: project-backup
 *
 * @param string $project_name
 *   Project name, if empty will fallback to default project.
 * @param string $env_name
 *   Environment name.
 */
function drush_project_tools_project_backup($project_name, $env_name) {
  _drush_project_tools_include();

  $project_name = $project_name ?: project_tools_get_default_project();
  $environment = project_tools_get_environment($project_name, $env_name);

  $site_alias = $environment['site_alias'];

  $backup_dir = $environment['backup_directory'];

  $file_name = $project_name . '-' . $env_name . '-' . date('Ymd-His') . '.sql';
  $backup_path = "$backup_dir/$file_name";

  $variables = [
      '@project' => $project_name,
      '@site' => $site_alias,
      '@environment' => $env_name,
      '@backup_path' => $backup_path,
  ];

  drush_print(dt("Backing-up @project/@environment, site @site to: @backup_path", $variables));

  $commandline_options = [
    "--result-file=$backup_path",
    '--structure-tables-key=common',
    '--gzip',
  ];
  $backend_options = [
      'interactive' => TRUE,
  ];

  $result = drush_invoke_process($site_alias, "sql-dump", [], $commandline_options, $backend_options);

  if ($result === FALSE || !empty($result['error_status'])) {
    return drush_set_error("Cancelled!!!");
  }

  drush_print("Backup finished");
}

/**
 * Include libraries for this version.
 */
function _drush_project_tools_include() {
  require_once __DIR__ . '/project_tools.inc.php';
  //$version = 'drupal' . drush_drupal_major_version();
  //require_once __DIR__ . '/project_tools.' . $version . '.inc';
}


