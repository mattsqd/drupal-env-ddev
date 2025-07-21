<?php

namespace RoboEnv\Robo\Plugin\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Run orchestration tasks for Ddev.
 *
 * @class RoboFile
 */
class DdevCommands extends CommonCommands
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
    public static function composerCommand($inside = TRUE): string
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
    public static function drushCommand($inside = TRUE): string
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
    public static function execCommand($inside = TRUE): string
    {
        if ($inside) {
            return 'bash -c';
        } else {
            return 'ddev exec';
        }
    }

    /**
     * Toggles when Ddev starts up, xdebug will now be on by default.
     *
     * @command ddev:xdebug-toggle-on-by-default
     */
    public function xdebugToggleOnByDefault(): void
    {
        $this->isInit();
        $yml_file = $this->getDdevLocalYml();
        $yml_value =& $yml_file['xdebug_enabled'];
        var_dump($yml_value);
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
     * Toggles XDebug to work on Drush commands.
     *
     * Xdebug must be enabled for this to work.
     *
     * https://ddev.readthedocs.io/en/stable/users/usage/cms-settings/#drush-and-xdebug
     *
     * @command ddev:xdebug-toggle-drush
     */
    public function xdebugToggleDrush(SymfonyStyle $io): void
    {
        $this->isInit();
        $io->writeln('Ensuring xdebug is enabled...');
        $this->_exec('ddev xdebug on');
        if ($this->getEnv('drush-allow-xdebug') !== '1') {
            $io->warning('This will cause warning messages to flood your console if your IDE is not listening for Xdebug connections. Instead, you can run drush with the --xdebug option to trigger Xdebug to connect on a per command basis.');
            if ($io->confirm('Do you want to continue?')) {
                $this->setEnv('drush-allow-xdebug', '1');
            } else {
                return;
            }
        } else {
            $this->setEnv('drush-allow-xdebug', '0');
            $io->writeln('You can still drush drush with the --xdebug option to trigger Xdebug to connect on a per command basis.');
        }
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
            throw new \Exception('Ddev file does not exist, please run ddev-admin:setup-project instead.');
        }
        return true;
    }

    /**
     * {@inheritDoc}
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
            throw new \Exception("{$this->ddev_yml_path} has not set project name yet, please run ddev-admin:setup-project instead.");
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
        $this->say("{$this->ddev_yml_path} has been written. Please commit this file so that ./robo.sh ddev:init can be run and the environment can be started by yourself and others. If the project has already been started, you will need to ddev rebuild -y");
        return $this->saveYml($this->ddev_yml_path, $file_contents);
    }

    /**
     * Configure Ddev so it can be started.
     *
     * * Creates the config.yaml file
     * * Set the PHP version
     *
     * @command ddev-admin:init
     */
    public function ddevAdminInit(SymfonyStyle $io): void
    {
        if ($this->doesDdevFileExists(true) && !$io->confirm("Ddev is already set up, are you sure you want to update your {$this->ddev_yml_path} file?", false)) {
            $this->say('Cancelled.');
            return;
        }
        $this->enterToContinue($io, 'Running through the interactive configuration of DDEV. This can be run by itself later via `./robo.sh ddev-admin:config`.');

        $this->_exec('vendor/bin/robo ddev-admin:config')->stopOnFail();

        $this->enterToContinue($io, 'Setting required shared services. This can be run by itself later via `./robo.sh ddev-admin:set-required-shared-services`.');
        $this->_exec('vendor/bin/robo ddev-admin:set-required-shared-services');

        $this->enterToContinue($io, 'DDEV will now start up and install Drupal so that the scripts can work on your current install.');
        $this->_exec('vendor/bin/robo ddev:init')->stopOnFail();

        $this->enterToContinue($io, 'Setting optional shared services. This can be run by itself later via `./robo.sh ddev-admin:set-optional-shared-services`.');
        $this->_exec('vendor/bin/robo ddev-admin:set-optional-shared-services');

        // The following are shared commands that are not specific to Ddev, but
        // instead just need a local installed to work.
        $this->enterToContinue($io, 'Taking action after a local has been installed. This can be run by itself later via `./robo.sh post-local-started`.');
        $this->_exec('vendor/bin/robo common-admin:post-local-started');

    }

    /**
     * Initial DDEV configuration.
     *
     * @command ddev-admin:config
     *
     * @return void
     */
    public function ddevAdminConfig(SymfonyStyle $io): void
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
                                ['exec-host' => './robo.sh common:remove-settings-php-changes'],
                            ],
                            'post-start' => [
                                ['exec' => 'env COMPOSER_DEV=1 ./orch/build.sh;'],
                                ['exec' => './orch/build_node.sh;'],
                                ['exec-host' => './robo.sh common:remove-settings-php-changes'],
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
     * Change the version of services always included.
     *
     * @command ddev-admin:set-required-shared-services
     *
     * @return void
     */
    public function ddevAdminSetRequiredSharedServices(SymfonyStyle $io): void
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
     * Add or remove additional shared services.
     *
     * @command ddev-admin:set-optional-shared-services
     *
     * @return void
     */
    public function ddevAdminSetOptionalSharedServices(SymfonyStyle $io): void
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
     * Copy the Solr config from Drupal to the Solr server config directory.
     *
     * @return void
     *
     * @command ddev-admin:solr-config
     */
    public function ddevAdminSolrConfig(SymfonyStyle $io): void
    {
        $this->isInit();
        $this->isDrupalInstalled($io);
        $this->drush($io, ['search-api-solr:get-server-config', 'default_solr_server', 'solr-config.zip']);
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
     * Ensure that an original install directory flag is set.
     *
     * This helps with the project copying functionality.
     *
     * @return string
     */
    protected function ensureOriginalInstallDirectorySet(): string
    {
        $original_install_directory = $this->getConfig('flags.ddev.originalInstallDirectory', '', true);
        if (!strlen($original_install_directory)) {
            $original_install_directory = basename(realpath(getcwd()));
            $this->saveConfig('flags.ddev.originalInstallDirectory', $original_install_directory, true);
        }
        return $original_install_directory;
    }

    /**
     * Start Ddev for the first time.
     *
     * * Ensures ddev is installed.
     * * Introduce to common shortcuts.
     * * Set DDEV to the default env.
     * * Starts up DDEV.
     * * Installs Drupal.
     * * Set personal services.
     *
     * @command ddev:init
     */
    public function ddevInit(SymfonyStyle $io): void
    {
        if (!$this->ddevReqs($io)) {
            throw new \Exception('Unable to find all requirements. Please re-run this command after installing');
        }
        // This will now be the default local environment, as many can be
        // installed. This allows the shortcuts like drush.sh to know which
        // environment to use.
        $this->setDefaultLocalEnvironment($this->getName());
        // Introduce the common shortcuts so one knows how they work and to
        // configure them.
        $this->introduceCommonShortcuts($io);
        // Check if the original directory has been captured yet, if not this
        // is the original install. It does not matter if that directory
        // does not exist anymore, it will not be copied from just it's name
        // used in new copied projects.
        // This allows the project to be copied from any descendant and use
        // the original directory + suffix.
        $this->ensureOriginalInstallDirectorySet();
        $this->_exec('ddev delete --omit-snapshot -y')->stopOnFail();
        $this->_exec('ddev start')->stopOnFail();
        $this->_exec('./robo.sh si')->stopOnFail();
        if ($io->confirm('Would you like to add completion for ddev commands to your shell?)')) {
            $this->_exec('ddev completion');
            $shell = $this->ask('What shell do you use from the above options? Hit enter to just skip this step.');
            if (strlen($shell)) {
                $this->taskExec('ddev')->args(['completion', $shell])->run();
                $this->enterToContinue($io, 'Please copy the above completion scripts and follow this documentation to install them https://apple.github.io/swift-argument-parser/documentation/argumentparser/installingcompletionscripts/');
            }
        }
        $io->success('Your environment has been started and Drupal site installed! Please use the one time login link to login.');
        $io->info('You have access to a couple very useful commands, `ddev phpmyadmin` and `ddev mailpit` for database and email access.');
        $io->info('How do I interact with my environment?');
        $io->info('Just like a normal DDEV site (https://ddev.readthedocs.io).');
        $io->info('What\'s different?');
        $io->info('You have two new robo "tooling" available: "./robo.sh si" will install a Drupal site from configuration. "./robo.sh su" will update an already installed site, like a normal production deployment without destroying the database.');
        $io->info('You now have access to helper commands via ./robo.sh. These can be found under the "common", "xdebug", and "ddev" namespaces. Those in the "common-admin" & "ddev-admin" will effect files that are committed and therefore all developers. You also have access to the common shortcuts. You can find and reset their paths via "./robo.sh common:shortcuts-help".');
        if ($io->confirm('Would you like to reset or learn more about shortcuts or reset their paths?', false)) {
            $this->_exec('./robo.sh common:shortcuts-help');
        }
        $xdebug_on = $io->choice('Would you like to enable Xdebug?', [
            'always' => 'Whenever the environment starts',
            'once' => 'Until the next restart',
            'no' => 'Not right now',
        ]);
        if ($xdebug_on !== 'no') {
            if ($xdebug_on === 'always') {
                $this->_exec('vendor/bin/robo ddev:xdebug-toggle-on-by-default');
            } elseif ($xdebug_on === 'once') {
                $this->_exec('ddev xdebug on');
            }
            if ($io->confirm('When Xdebug is on, would you like to to debug Drush commands as well?')) {
                $this->_exec('vendor/bin/robo ddev:xdebug-toggle-drush');
            }
        }

        $io->writeln('Run ddev launch visit the environment or ./drush.sh uli to get a login link.');;
        // @todo: Ask to enable local settings (no cache / twig debug).
    }

    /**
     * Display the requirements to use Ddev.
     *
     * @command ddev:reqs
     *
     * @return bool
     *   True if all requirements are installed.
     *
     * @throws \Exception
     */
    public function ddevReqs(SymfonyStyle $io): bool
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
     * Get a project folder name that is a sibling.
     *
     * @param string $sibling_project_folder_name
     *
     * @return string
     */
    protected function getSiblingAbsDir(string $sibling_project_folder_name): string
    {
        return realpath(getcwd() . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . $sibling_project_folder_name;
    }

    /**
     * Create a duplicate local environment.
     *
     * * Copy all files to a new directory
     * * Cleans up IDE settings for new directory name.
     *
     * @command ddev:duplicate-project
     */
    public function ddevDuplicateProject(SymfonyStyle $io): void
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
