<?php

namespace Drush\project_tools\Commands;

use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteProcess\SiteProcess;

/**
 * Share some drush command helper methods.
 */
trait ProcessHelperCommandsTrait {
  use SiteAliasManagerAwareTrait;

  /**
   * Prints output with replacements.
   *
   * @param string $message
   *
   * @param array $variables
   */
  protected function printMessage($message, $variables = array()) {
    $this->output()->writeln(StringUtils::interpolate($message, $variables));
  }

  /**
   * Runs other drush commands on the same site.
   *
   * @see \Consolidation\SiteProcess\ProcessManagerAwareInterface::drush()
   */
  protected function runDrushCommand($command, $arguments = [], $options = [], $options_double_dash = []) {
    $this->printMessage("INVOKE: drush @name @arguments @options",[
      '@name' => $command,
      '@arguments' => implode(' ', $arguments),
      '@options' => implode(' ', $options),
    ]);
    $self = $this->siteAliasManager()->getSelf();
    $process = $this->processManager()->drush($self, $command, $arguments, $options, $options_double_dash);

    return $this->runProcess($process);
  }

  /**
   * Run multiple drush commands through drush invoke.
   *
   * @param array $commands
   *   Array of commands, each an array with [ name, arguments, options]
   *
   */
  protected function runDrushCommandList(array $commands) {
    $return = TRUE;

    foreach ($commands as $command) {
      $command = array_merge($command, [[], []]);
      list($name, $arguments, $opts) = $command;

      // Drush 9 invoke drush.
      $result = $this->runDrushCommand($name, $arguments, $opts);

      if ($result === FALSE) {
        throw new \Exception(sprintf("Error running drush command: %s", $name));
        $return = FALSE;
        break;
      }

    }

    return $return;
  }

  /**
   * Run shell command.
   */
  protected function runShellCommand($command, $cwd = null) {
    $pwd = $cwd ? $cwd : getcwd();
    $this->output()->writeln(sprintf("SHELL: %s [%s]", $command, $pwd));

    $process = Drush::shell($command, $cwd);
    return $this->runProcess($process);

  }

  /**
   * Run process and print output.
   *
   * @param \Consolidation\SiteProcess\SiteProcess $process
   *
   * @return boolean
   *   Process result, TRUE if successful.
   */
  protected function runProcess(SiteProcess $process) {
    $process->enableOutput();
    $process->run();

    if ($process->isSuccessful()) {
      $this->output()->writeln($process->getOutput());
    }
    else {
      Drush::logger()->debug($process->getErrorOutput());
    }

    return $process->isSuccessful();
  }
}
