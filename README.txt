Project Tools
=============

Introduction
------------
This is a collection of development and deployment tools to use with Drupal and Drush.
It creates drush commands for:

* Running all site update scripts (update database, clear caches, revert configuration, etc..)
* Reloading database from one environment to another. Example: drush devel-reload-from-prod
* Deployment using ansible playbooks, other methods coming...

This is a development tool by https://reyero.net
Use at your own risk.

Requirements
------------
* Drupal 7 or Drupal 8, https://drupal.org
* Drush 8.x, http://docs.drush.org/en/8.x/
* Ansible, https://www.ansible.com/
* Features, https://www.drupal.org/project/features

Installation
------------

* Via composer:
  composer require reyero/project_tools

* Manual download:
  * Drupal 7: Download and place under DRUPAL_ROOT/sites/all/drush
  * Drupal 8: Download and place under DRUPAL_ROOT/drush

Configuration
-------------

0. Create drush aliases for each of your sites/environments like:

   @myproject.prod
   @myproject.devel
   

1. Define your project/s in drushrc.php

// CUSTOMIZE: Base directory for the full project, typically this is the root
// of your git repository, one level below the Drupal docroot.
$project_base_dir = realpath(dirname(__DIR__, 4));

// CUSTOMIZE: Use whatever shorthand project name
$project_name = 'myproject';

// Most of these settings can be defined per project and then overridden per environment.
$options['project_settings'][$project_name] = [
    'project_label' => 'My Project',    
    // Modules to enable when a site is deployed / udpated
    'master_modules' => ['mysite_custom_master'],
    'base_directory' => $project_base_dir,
    'backup_directory' => "$project_base_dir/backups",   
    // Ansible configuration
    'ansible_directory' => "$project_base_dir/ansible",
    'ansible_playbooks' => [
      'checkout' => 'checkout.yml',
      'deploy' => 'deploy.yml',
    ],
];


2. Define your project environments in drushrc.php.

// Note environment names are arbitrary, though there are some safewards for not to trash
// the environment named 'production'.

$options['project_environments'][$project_name]['production'] = [
    'site_alias' => '@myproject.prod',
    'ansible_inventory' => 'hosts/production',
    'deployment_strategy' => 'ansible',
    // You can override directories per environment.
    'backup_directory' => '/var/backups/myproject',
    // Modules to enable when any site is deployed / updated
    'environment_modules' => ['myproject_site_env_prod'],
];

$options['project_environments'][$project_name]['devel'] = [
    'site_alias' => '@myproject.devel',
    'reload_source' => 'production',
    // Modules to enable when the site is deployed / udpated
    'environment_modules' => ['myproject_site_env_devel'],
];


3. (Optional) Define some shorthand drush commands in drushrc.php

$options['shell-aliases']['devel-update'] = "project-update $project_name devel";
$options['shell-aliases']['devel-reload'] = "project-reload $project_name devel production --skip-backup";

$options['shell-aliases']['myproject-deploy-production'] = "project-deploy $project_name production";
$options['shell-aliases']['myproject-backup-production'] = "project-backup $project_name production";


