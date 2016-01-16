<?php
date_default_timezone_set('Europe/Stockholm');

include_once 'vendor/deployer/deployer/recipe/common.php';

env('remote.name','tygvaskan');
env('remote.path','/mnt/persist/www/tygvaskan.se');
env('remote.ssh','root@andreasek.se');
env('remote.database','tygvaskan');
env('remote.domain','tygvaskan.se');
env('local.domain','tygvaskan.dev');
env('local.is_elastic',false);

server( 'development', 'tygvaskan.dev', 22 )
    ->env('deploy_path','/var/www/tygvaskan')
    ->env('branch', 'master')
    ->stage('development')
    ->user( 'vagrant', 'vagrant' );

server( 'production', 'andreasek.se', 22 )
    ->env('deploy_path','/mnt/persist/www/tygvaskan.se')
    ->user( 'root' )
    ->env('branch', 'master')
    ->stage('production')
    ->identityFile();

set('repository', 'git@github.com:ekandreas/tygvaskan.git');

// Symlink the .env file for Bedrock
set('env', 'prod');
set('keep_releases', 10);
set('shared_dirs', ['web/app/uploads','web/app/themes/tygvaskan/.cache']);
set('shared_files', ['.env', 'web/.htaccess', 'web/robots.txt']);
set('env_vars', '/usr/bin/env');

task('deploy:restart', function () {
    writeln('Purge cache...');
    //run( 'rm -Rf web/app/uploads/.cache/*.*' );
    //run( 'chmod -R 777 web/app/uploads' );
})->desc('Empty cache');

task( 'deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:shared',
    'deploy:symlink',
    'cleanup',
    'deploy:restart',
    'success'
] )->desc( 'Deploy your Bedrock project, eg dep deploy production' );
