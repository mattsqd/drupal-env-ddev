<?php

namespace DrupalEnvDdev\Robo\Plugin\Commands;

use DrupalEnv\Robo\Plugin\Commands\DrupalEnvCommandsBase;

/**
 * Provide commands to handle installation tasks.
 *
 * @class RoboFile
 */
class DrupalEnvDdevCommands extends DrupalEnvCommandsBase
{

    /**
     * {@inheritdoc}
     */
    protected string $package_name = 'mattsqd/drupal-env-ddev';

    /**
     * Update the environment so that the scaffolding can happen, and run it.
     *
     * @command drupal-env-ddev:scaffold
     */
    public function scaffold(string $package_name = ''): void
    {
        // Only reason this method is redefined is so that it can be given
        // a new @command name.
        parent::scaffold($package_name);
    }

    /**
     * {@inheritdoc}
     */
    protected function preScaffoldChanges(): void
    {
        // Must make sure we remove any previous DDEV config files as our scripts
        // only modify and the existing stuff will stay and mess things up.
        if (is_dir('.ddev') && !file_exists('.drupal-env-ddev-scaffolded')) {
            if ($this->confirm('You already seem to have DDEV configured locally. Continuing with this scaffolding will overwrite your current DDEV configuration. If you continue, ensure your .ddev files are committed so you can compare after. Continue?')) {
                $this->taskFilesystemStack()
                    ->rename('.ddev', '.ddev.old')
                    ->run();
            }
        }
    }

}
