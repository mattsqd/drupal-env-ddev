<?php

/**
 * @file
 * Configure settings.php for DDEV.
 */

include $app_root . '/' . $site_path . '/settings.ddev.php';
// Include settings required for Redis cache.
if (file_exists(__DIR__ . '/settings.ddev.redis.php')) {
    include __DIR__ . '/settings.ddev.redis.php';
} elseif (getenv('DDEV_PLUGIN_INSTALLED_MEMCACHED') === '1') {
    require_once 'settings.memcache.php';
    if (function_exists('_drupal_env_settings_memcache')) {
        $memcache_host = 'memcached:11211';
        _drupal_env_settings_memcache($settings, $memcache_host);
    }
}

// https://github.com/ddev/ddev-drupal-solr?tab=readme-ov-file#installation-on-drupal-9
if (getenv('DDEV_PLUGIN_INSTALLED_DRUPAL_SOLR') === '1') {
    $config['search_api.server.default_solr_server']['backend_config']['connector_config'] = [
        'core' => 'dev',
        'path' => '/',
        'host' => 'solr',
        'port' => '8983',
    ];
}
if (getenv('DDEV_PLUGIN_INSTALLED_ELASTICSEARCH') === '1') {
    $config['search_api.server.default_elasticsearch_server']['backend_config']['connector_config']['url'] = sprintf(
        'http://%s:%d',
        'elasticsearch',
        '9200',
    );
}
