<?php

namespace Deployer;

$env = \Dotenv\Dotenv::createImmutable(__DIR__)->load();

require 'recipe/laravel.php';
require 'recipe/yarn.php';
require 'contrib/php-fpm.php';
require 'contrib/npm.php';

set('application', 'SiVoteSender');
set('repository', 'git@github.com:Institut-IP21/SiVoteSender');
set('php_fpm_version', '7.4');

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
    'artisan:migrate',
    'deploy:publish',
]);

task('yarn', function () {
    cd('{{release_or_current_path}}');
    run('yarn');
});

task('yarn:production', function () {
    cd('{{release_or_current_path}}');
    run('yarn production');
});

task('artisan:model:scan', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan model:scan');
});

task('artisan:route:scan', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan route:scan');
});

task('artisan:evote:cache', function () {
    cd('{{release_or_current_path}}');
    echo run('php artisan evote:cache');
});

after('deploy:failed', 'deploy:unlock');
