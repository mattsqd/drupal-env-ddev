<?php

namespace DrupalEnvDdev\Robo\Plugin\Commands;

use DrupalEnv\Robo\Plugin\Commands\DrupalEnvCommandsBase;
use Symfony\Component\Console\Style\SymfonyStyle;

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
     * This is the entry point to allow Drupal env and it's plugins to scaffold.
     *
     * Run this to kick off once.
     *
     * @command drupal-env-ddev:enable-scaffold
     */
    public function enableScaffoldCommand(SymfonyStyle $io): void
    {
        $this->enableScaffolding($io);
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeEnableScaffolding(SymfonyStyle $io): void
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

    /**
     * {@inheritdoc}
     */
    public static function preScaffoldCommand(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function postScaffoldCommand(): array {
        return [];
    }

}
