<?php

declare(strict_types=1);

namespace DrupalEnvDdev\Environment;

use DrupalEnv\Environment\Environment;
use DrupalEnv\Environment\Feature\SupportsDuplicate;
use DrupalEnv\Environment\Feature\SupportsSolrConfig;
use DrupalEnv\Environment\Feature\SupportsXdebugDrush;
use DrupalEnv\Environment\Feature\SupportsXdebugOnByDefault;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * DDEV local environment.
 */
class DdevEnvironment extends Environment implements SupportsXdebugOnByDefault, SupportsXdebugDrush, SupportsDuplicate, SupportsSolrConfig
{

    /**
     * The path to the .ddev.local.yaml.
     *
     * @var string
     */
    protected string $ddev_local_yml_path = '.ddev/config.local.yaml';

    /**
     * The path to the .ddev.yaml.
     *
     * @var string
     */
    protected string $ddev_yml_path = '.ddev/config.yaml';

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'ddev';
    }

    /**
     * {@inheritDoc}
     */
    public static function getLabel(): string
    {
        return 'DDEV';
    }

    /**
     * {@inheritDoc}
     */
    public static function composerCommand(bool $inside = true): string
    {
        if ($inside) {
            return 'composer';
        } else {
            return 'ddev composer';
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function drushCommand(bool $inside = true): string
    {
        if ($inside) {
            return 'drush';
        } else {
            return 'ddev drush';
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function execCommand(bool $inside = true): string
    {
        if ($inside) {
            return 'bash -c';
        } else {
            return 'ddev exec';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function xdebugToggleOnByDefault(SymfonyStyle $io): void
    {
        $this->isInit();
        $yml_file = $this->getDdevLocalYml();
        $yml_value =& $yml_file['xdebug_enabled'];
        if ($yml_value === true) {
            $this->yell('Xdebug is enabled by default, disabling now.');
            $yml_value = false;
            $this->_exec('ddev xdebug off');
        } else {
            $this->yell('Enabling Xdebug by default.');
            $yml_value = true;
            $this->_exec('ddev xdebug on');
        }
        $this->saveDdevLocalYml($yml_file);
    }

    /**
     * {@inheritDoc}
     *
     * https://ddev.readthedocs.io/en/stable/users/usage/cms-settings/#drush-and-xdebug
     */
    public function xdebugToggleDrush(SymfonyStyle $io): void
    {
        $this->isInit();
        $io->writeln('Ensuring xdebug is enabled...');
        $this->_exec('ddev xdebug on');
        $value = 'DRUSH_ALLOW_XDEBUG=1';
        $yml_file = $this->getDdevLocalYml();
        $yml_value =& $yml_file['web_environment'];
        if (is_array($yml_value) && in_array($value, $yml_value)) {
            $this->yell('Drush Xdebug is enabled, disabling now.');
            unset($yml_value[array_search($value, $yml_value)]);
            if (empty($yml_file['web_environment'])) {
                unset($yml_file['web_environment']);
            } else {
                // Make sure keys are in order.
                $yml_file['web_environment'] = array_values($yml_file['web_environment']);
            }
        } else {
            $this->yell('Drush Xdebug is disabled, enabling now.');
            $yml_value[] = $value;
        }
        $this->saveDdevLocalYml($yml_file);
        $this->rebuildRequired($io, true);
    }

    /**
     * Initializes and returns the array value of config.local.yaml.
     *
     * @return array
     */
    protected function getDdevLocalYml(): array
    {
        if (!file_exists($this->ddev_local_yml_path)) {
            $this->taskFilesystemStack()->touch($this->ddev_local_yml_path)->run();
        }
        return Yaml::parse(file_get_contents($this->ddev_local_yml_path)) ?? [];
    }

    /**
     * Returns the array value of .config.yaml.
     *
     * @return array
     *   If 'name' is not set, an empty array will be returned.
     */

    protected function getDdevYml(): array
    {
        $default = [];
        if (!file_exists($this->ddev_yml_path)) {
            return $default;
        }
        return Yaml::parse(file_get_contents($this->ddev_yml_path)) ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function isSetUp(): bool
    {
        return $this->doesDdevFileExists(true);
    }

    /**
     * Requires ddev file to exist.
     *
     * @param bool $return
     *   If true, returns false instead of an exception.
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function doesDdevFileExists(bool $return = false): bool
    {
        if (!file_exists($this->ddev_yml_path)) {
            if ($return) {
                return false;
            }
            throw new \Exception('Ddev file does not exist, please run denv-admin:local-setup instead.');
        }
        return true;
    }

    /**
     * Requires the DDEV config to exist and have a project name.
     */
    protected function isInit(bool $return = false): bool
    {
        if (!$this->doesDdevFileExists($return)) {
            return false;
        }
        $ddev_yml = $this->getDdevYml();
        if (empty($ddev_yml['name'])) {
            if ($return) {
                return false;
            }
            throw new \Exception("{$this->ddev_yml_path} has not set project name yet, please run denv-admin:local-setup instead.");
        }
        return true;
    }

    /**
     * Save the $file_contents to config.local.yaml.
     *
     * @param array|string $file_contents
     *   A string of yaml or an array.
     *
     * @return bool
     */
    protected function saveDdevLocalYml(array|string $file_contents): bool
    {
        return $this->saveYml($this->ddev_local_yml_path, $file_contents);
    }

    /**
     * Save the $file_contents to config.yaml.
     *
     * @param array|string $file_contents
     *   A string of yaml or an array.
     *
     * @return bool
     */
    protected function saveDdevYml(array|string $file_contents): bool
    {
        $this->say("{$this->ddev_yml_path} has been written. Please commit this file so that ./robo.sh denv:local-init can be run and the environment can be started by yourself and others. If the project has already been started, you will need to ddev rebuild -y");
        return $this->saveYml($this->ddev_yml_path, $file_contents);
    }

    /**
     * {@inheritDoc}
     */
    public function config(SymfonyStyle $io): void
    {
        while (true) {
            if ($this->taskExec('ddev')->args('config')->run()->wasSuccessful()) {
                // Add hooks to config.yaml if they are not there yet.
                $ddev_yml = $this->getDdevYml();
                if (!isset($ddev_yml['hooks'])) {
                    $last_key = array_key_last($ddev_yml);
                    $last_value = $ddev_yml[$last_key];
                    $ddev_yml_changes = [
                        $last_key => $last_value,
                        'hooks' => [
                            'post-config' => [
                                ['exec-host' => './robo.sh denv:remove-settings-php-changes'],
                            ],
                            'post-start' => [
                                ['exec' => 'env COMPOSER_DEV=1 ./orch/build.sh;'],
                                ['exec' => './orch/build_node.sh;'],
                                ['exec-host' => './robo.sh denv:remove-settings-php-changes'],
                            ]
                        ]
                    ];
                    $this->taskReplaceInFile($this->ddev_yml_path)->from(
                        Yaml::dump([$last_key => $last_value])
                    )->to(Yaml::dump($ddev_yml_changes))->run();
                }
                $io->writeln('Let DDEV know this is a Drupal Env local environment.');
                $this->setEnv('drupal-env-local', '1');
                break;
            }
        }
    }

    /**
     * Set a key value in .ddev/.env.
     *
     * These environment variables will be loaded in the web app container.
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     */
    protected function setEnv(string $key, string $value): bool {
        return $this->taskExec('ddev')->args(['dotenv', 'set', '.ddev/.env/'])->option($key, $value)->run()->wasSuccessful();
    }

    /**
     * Get a key value in .ddev/.env.
     *
     * These environment variables will be loaded in the web app container.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getEnv(string $key): string {
        return $this->taskExec('ddev')->args(['dotenv', 'get', '.ddev/.env/'])->option($key)->printOutput(false)->run()->getMessage();
    }

    /**
     * Return the effective value of all config files for a given key.
     *
     * @param string $config_key
     *
     * @return string|bool
     *   The value or false if key does not exist.
     */
    protected function getEffectiveConfigValue(string $config_key): string|bool
    {
        $output = $this->taskExec('ddev')->args(['debug', 'e'])->printOutput(false)->run()->getMessage();
        $values = explode("\n", $output);
        // The first line lists the yaml files loaded.
        unset($values[0]);
        foreach ($values as $value) {
            $value = explode(': ', $value);
            if ($value[0] === $config_key) {
                if (str_starts_with($value[1], '{')) {
                    $value[1] = str_replace(['{', '}', ' '],
                        ['', '', ':'],
                        $value[1]);
                }
                return $value[1];
            }
        }
        return false;
    }

    protected function getConfigFileValue(array $yml, string $config_key): string|bool {
        $value = $yml[$config_key] ?? false;
        if (is_array($value)) {
            $value = implode(':', $value);
        }
        return $value;
    }

    /**
     * Set the versions of services provided by default from the Recipe.
     *
     * @param SymfonyStyle $io
     * @param string $config_key
     * @param string $description
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    protected function askConfigYaml(SymfonyStyle $io, string $config_key, string $description): ?bool
    {
        $this->isInit();
        $effective_value = $this->getEffectiveConfigValue($config_key) ?: '<no effective value>';
        $config_value = $this->getConfigFileValue($this->getDdevYml(), $config_key) ?: '<not set>';;
        $config_local_value = $this->getConfigFileValue($this->getDdevLocalYml(), $config_key) ?: '<not overriden>';
        $io->section("Current configuration value for key '$config_key'.");
        $io->note('Passing an invalid value will let you see the options that are available, then you can try again right away.');;
        $table = new Table($io);
        $table->setHeaders(['Shared (config.yaml)', 'Local (Personal Override)', 'Effective Value']);
        $table->setRows([[$config_value, $config_local_value, $effective_value]]);
        $table->render();
        while (true) {
            $additional_info = '';
            if ($config_key === 'database') {
                $additional_info = ' If the database already exists, you may get a get an error about not being able to switch. If you do, type "delete"';
            }
            $new_value = $this->askDefault(
                "What value would you like to set for your $description?$additional_info",
                $config_value
            );
            if ($config_key === 'database' && $new_value === 'delete') {
                $this->taskExec('ddev')->args('delete')->option('omit-snapshot')->run();
                continue;
            }
            if ($new_value === $config_value) {
                $this->yell('No change made.');
                return true;
            }
            if ($this->taskExec('ddev')->args('config')->option(
                str_replace('_', '-', $config_key),
                $new_value,
            )->run()->wasSuccessful()) {
                break;
            }
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function servicesRequired(SymfonyStyle $io): void
    {
        $this->isInit();
        $io->warning('Drupal requirements for Web Servers: https://www.drupal.org/docs/getting-started/system-requirements/web-server-requirements');
        $this->askConfigYaml($io, 'webserver_type', 'Web Server');
        $io->warning('Drupal requirements for Databases: https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements');
        $this->askConfigYaml($io, 'database', 'Database Server');
        $io->warning('Drupal requirements for PHP: https://www.drupal.org/docs/getting-started/system-requirements/php-requirements#versionsn');
        $this->askConfigYaml($io, 'php_version', 'PHP Version');
        $this->enterToContinue($io, 'If you need to take action during the build process, please edit the "./orch/build.sh" file. By default, this file will install composer dependencies.');
        $this->askConfigYaml($io, 'nodejs_version', 'NodeJS Version');
        $this->enterToContinue($io, 'If you need to take action during the build process, please edit the "./orch/build_node.sh" file. By default, this file will install deps with npm and run "gulp" on all custom themes that have a package.json.');
    }

    /**
     * {@inheritDoc}
     */
    public function servicesOptional(SymfonyStyle $io): void
    {
        $this->isInit();
        $this->isDrupalInstalled($io);

        $this->toggleAddonsTypes($io, 'cache server', ['memcached', 'redis']);
        $this->toggleAddonsTypes($io, 'search server', ['drupal-solr', 'elasticsearch']);
    }

    /**
     * Turn on or off a group of plugins.
     *
     * @param string $type_description
     * @param array $plugin_ids
     *
     * @throws \Exception
     */
    protected function toggleAddonsTypes(SymfonyStyle $io, string $type_description, array $plugin_ids): void {
        while (true) {
            $enabled = [];
            $options = [];
            foreach ($plugin_ids as $plugin_id) {
                $enabled[$plugin_id] = $this->ddevIsPluginInstalled($io, $plugin_id);
                $options[$plugin_id] = $plugin_id . ($enabled[$plugin_id] ? ' (INSTALLED, choose to uninstall)' : ' (not installed)');
            }
            $options['skip'] = 'Do Nothing';
            $plugin_choice_id = $io->choice(
                "Optionally choose a $type_description.",
                $options,
                'skip'
            );

            if ($plugin_choice_id === 'skip') {
                break;
            }
            if (strlen($plugin_choice_id)) {
                $status = $enabled[$plugin_choice_id] ? null : true;
                $this->reactToSharedService(
                    $io,
                    $plugin_choice_id,
                    $status
                );
                $this->setEnv("ddev-plugin-installed-" . str_replace('_', '-', $plugin_choice_id), (int) $status);
                $this->taskExec('ddev')->args(
                    [
                        'addon',
                        $status ? 'get' : 'remove',
                        "ddev/ddev-$plugin_choice_id"
                    ]
                )->run();
                $this->removeSettingsPhpChanges($io);
                $this->rebuildRequired($io, true, "the $plugin_choice_id plugin has changed");
            }
        }
    }

    /**
     * Is a DDEV plugin installed.
     *
     * @param string $plugin_name
     *
     * @return bool
     */
    protected function ddevIsPluginInstalled(SymfonyStyle $io, string $plugin_name): bool
    {
        $output = $this->taskExec('ddev')
            ->args(['addon', 'list'])
            ->option('installed')
            ->printOutput(false)
            ->silent(true)
            ->run()->getMessage();
        return str_contains($output, "ddev/ddev-$plugin_name");
    }

    /**
     * Helper to allow for fast rebuild.
     *
     * @param bool $rebuild_required
     *   If true, a "confirm" will be shown to rebuild ddev.
     *
     * @return void
     */
    protected function rebuildRequired(SymfonyStyle $io, bool $rebuild_required, string $confirm_message = ''): void
    {
        if (strlen($confirm_message)) {
            $confirm_message = "A DDEV restart is required because $confirm_message, please confirm to do so.";
        } else {
            $confirm_message = "A DDEV restart is required, please confirm to do so.";
        }
        if ($rebuild_required && $io->confirm($confirm_message, false)) {
            $this->_exec('ddev restart');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function solrConfig(SymfonyStyle $io): void
    {
        $this->isInit();
        $this->isDrupalInstalled($io);
        $this->_exec(static::drushCommand() . ' search-api-solr:get-server-config default_solr_server solr-config.zip');
        if ($this->taskDeleteDir('solr-conf')
            ->taskExtract('web/solr-config.zip')
            ->to('solr-conf')
            ->taskFilesystemStack()
            ->remove('web/solr-config.zip')
            ->stopOnFail()
            ->run()
            ->wasSuccessful()) {
            $io->note('Latest solr config downloaded and extracted.');
        }

        $this->taskDeleteDir('.ddev/solr/conf')->run();
        $this->_copyDir('solr-conf', '.ddev/solr/conf');
        $this->taskDeleteDir('solr-conf')->run()->wasSuccessful();
        $io->note(
            'Latest solr config moved to the solr server config directory.'
        );
        $this->rebuildRequired($io, true, 'the Solr configuration is now in place, you can commit the files in ".ddev/solr/conf"');
    }

    /**
     * {@inheritDoc}
     */
    public function rebuild(SymfonyStyle $io): void
    {
        $this->_exec('ddev delete --omit-snapshot -y')->stopOnFail();
        $this->_exec('ddev start')->stopOnFail();
    }

    /**
     * {@inheritDoc}
     */
    public function afterInit(SymfonyStyle $io): void
    {
        if ($io->confirm('Would you like to add completion for ddev commands to your shell?')) {
            $this->_exec('ddev completion');
            $shell = $this->ask('What shell do you use from the above options? Hit enter to just skip this step.');
            if (strlen($shell)) {
                $this->taskExec('ddev')->args(['completion', $shell])->run();
                $this->enterToContinue($io, 'Please copy the above completion scripts and follow this documentation to install them https://apple.github.io/swift-argument-parser/documentation/argumentparser/installingcompletionscripts/');
            }
        }
        $io->info('You have access to a couple very useful commands, `ddev phpmyadmin` and `ddev mailpit` for database and email access.');
        $io->info('How do I interact with my environment?');
        $io->info('Just like a normal DDEV site (https://ddev.readthedocs.io).');
        $xdebug_on = $io->choice('Would you like to enable Xdebug?', [
            'always' => 'Whenever the environment starts',
            'once' => 'Until the next restart',
            'no' => 'Not right now',
        ]);
        if ($xdebug_on !== 'no') {
            if ($xdebug_on === 'always') {
                $this->runStep('denv:xdebug-toggle-on-by-default');
            } elseif ($xdebug_on === 'once') {
                $this->_exec('ddev xdebug on');
            }
            if ($io->confirm('When Xdebug is on, would you like to to debug Drush commands as well?')) {
                $this->runStep('denv:xdebug-toggle-drush');
            }
        }
        $io->writeln('Run ddev launch visit the environment or ./drush.sh uli to get a login link.');
    }

    /**
     * {@inheritDoc}
     */
    public function reqs(SymfonyStyle $io): bool
    {
        $rows = [];
        $missing_software = FALSE;
        if (!$this->addSoftwareTableRow(
            'DDEV',
            'ddev',
            'https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/',
            'https://ddev.readthedocs.io/en/stable/users/install/docker-installation/',
            $rows
        )) {
            $missing_software = TRUE;
        }
        $this->printSoftWareTable($io, $rows, $missing_software);
        return !$missing_software;
    }

    /**
     * {@inheritDoc}
     */
    public function duplicate(SymfonyStyle $io): void
    {
        $this->isInit();
        $ddev_file_yml = $this->getDdevYml();
        $io->note('Duplicating your project will copy the current root directory to a sibling directory so that they are separate environments.');
        $original_install_directory = $this->ensureOriginalInstallDirectorySet();
        $option_directory_suffix = $io->ask("Which directory do you want to put your project in? Note that it will be prefixed with '$original_install_directory-'", 'testing');
        // Remove the current directory from the new suffix if they entered it.
        $search = [$original_install_directory . '_', $original_install_directory, $original_install_directory . '-', ' '];
        $option_directory_suffix = str_replace($search, '', $option_directory_suffix);
        if (!strlen($option_directory_suffix)) {
            $this->yell('You have to enter a value that is not your current project directory.');
            return;
        }
        $new_project_name = $ddev_file_yml['name'] . '-' . $option_directory_suffix;
        $source_dir = realpath(getcwd());
        // The new directory is always based on the original install directory
        // in case the project is being copied from a copy.
        $target_dir = $this->getSiblingAbsDir($original_install_directory . '-' . $option_directory_suffix);
        if (is_dir($target_dir)) {
            $this->yell("The target directory $target_dir already exists, unable to create duplicate project.");
            return;
        }
        $this->say(sprintf('Your new duplicate project will be copied from %s to %s', $source_dir, $target_dir));
        if (!$io->confirm('Are you sure you want to continue?')) {
            $this->say('Cancelled');
            return;
        }
        $this->say('This can take a while, please don\'t close this process...');
        // $this->taskCopyDir() will make copies of symlink source, so use `cp`
        // instead.
        `cp -a $source_dir $target_dir`;
        if (!is_dir($target_dir)) {
            $this->yell('There was an error copying the folder structure.');
            return;
        }
        $this->say('Your local environment has been created');

        // Move into the new environment and do some cleanup on PhpStorm files.
        // And also be able to work on the new environment's config.local.yaml.
        chdir($target_dir);
        // Remove the workspace which contains unimportant files.
        if (file_exists('.idea/workspace.xml')) {
            $this->taskFilesystemStack()
                ->remove('.idea/workspace.xml')
                ->run();
        }
        $source_dir_name = basename($source_dir);
        $target_dir_name = basename($target_dir);
        // The .iml file needs to be renamed to the new dir if it exists.
        if (file_exists(".idea/$source_dir_name.iml")) {
            $this->taskFilesystemStack()
                ->rename(".idea/$source_dir_name.iml", ".idea/$target_dir_name.iml")
                ->run();
            // Now that the iml file has changed names, update its reference in
            // modules.xml.
            if (file_exists('.idea/modules.xml')) {
                $this->taskReplaceInFile('.idea/modules.xml')
                    ->from(".idea/$source_dir_name.iml")
                    ->to(".idea/$target_dir_name.iml")
                    ->run();
            }
        }

        // Every ddev project needs a unique name, otherwise they will use
        // the same containers. Override name in config.yaml with a new one
        // in config.local.yaml.
        $ddev_local_yml = $this->getDdevLocalYml();
        $ddev_local_yml['name'] = $new_project_name;
        $this->saveDdevLocalYml($ddev_local_yml);

        $this->say("Finished creating your new environment. Please change to the directory ../$target_dir_name first. It is ready to be started.");
    }

}
