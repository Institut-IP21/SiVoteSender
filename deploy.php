<?php

namespace Deployer;

use Dotenv;

require './vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->load();

require 'recipe/laravel.php';
require 'contrib/php-fpm.php';
require 'contrib/npm.php';

set('application', 'SiVoteSender');
set('repository', 'git@github.com:Institut-IP21/SiVoteSender');
set('php_fpm_version', '8.0');

host('staging')
    ->set('labels', ['stage' => 'staging'])
    ->set('hostname', function () {
        return env('DEPLOY_HOSTNAME_STAGING');
    })
    ->set('remote_user', function () {
        return env('DEPLOY_USER_STAGING');
    })
    ->set('deploy_path', function () {
        return env('DEPLOY_DIRECTORY_STAGING');
    })
    ->set('shared_files', ['.env', 'etc/nginx.conf'])
    ->set('shared_dirs', ['storage']);

host('production')
    ->set('labels', ['stage' => 'production'])
    ->set('hostname', function () {
        return env('DEPLOY_HOSTNAME_PRODUCTION');
    })
    ->set('remote_user', function () {
        return env('DEPLOY_USER_PRODUCTION');
    })
    ->set('deploy_path', function () {
        return env('DEPLOY_DIRECTORY_PRODUCTION');
    })
    ->set('shared_files', ['.env', 'etc/nginx.conf'])
    ->set('shared_dirs', ['storage']);


task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:evote:cache',
    'secure:config-cache',
    'artisan:migrate',
    'deploy:publish',
]);

task('bun:install', function () {
    cd('{{release_or_current_path}}');
    run('bun install');
});

task('bun:production', function () {
    cd('{{release_or_current_path}}');
    run('bun run production');
});

task('artisan:model:scan', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan model:scan');
});

task('artisan:evote:cache', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan evote:cache');
});

task('secure:config-cache', function () {
    $f = '{{release_path}}/bootstrap/cache/config.php';
    run("if [ -f $f ]; then chgrp {{http_user}} $f && chmod 640 $f || echo 'WARNING: config cache left unrestricted'; fi");
});

// Parity with web_app / web_engine: after the symlink flip, restart php-fpm so
// workers drop the previous release's realpath/opcache. web_sender exposes no
// @vite/hashed browser assets today (mail templates only), so it is not affected
// by the asset-hash staleness bug that hit web_app; this is preventive/parity.
// The sender box's deploy user does NOT (yet) have NOPASSWD sudo for this, so
// the task warns instead of failing the deploy. Grant it via infra to activate.
task('php-fpm:restart', function () {
    run('if sudo -n /usr/bin/systemctl restart php-fpm 2>/dev/null; then echo "php-fpm restarted"; else echo "NOTE: php-fpm not restarted — deploy user lacks NOPASSWD sudo on this host"; fi');
})->desc('Restart php-fpm after the symlink flip (no-op if sudo not granted)');

after('deploy:symlink', 'php-fpm:restart');

after('deploy:failed', 'deploy:unlock');
