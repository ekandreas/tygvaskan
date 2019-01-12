<?php
namespace Deployer;

include_once 'vendor/deployer/deployer/recipe/composer.php';

host('178.62.249.226')
    ->port(22)
    ->set('deploy_path', '~/www.tygvaskan.se')
    ->user('forge')
    ->set('branch', 'master')
    ->stage('production')
    ->identityFile('~/.ssh/id_rsa');

set('repository', 'git@github.com:ekandreas/tygvaskan.git');

// Symlink the .env file for Bedrock
set('keep_releases', 10);
set('shared_dirs', ['web/app/uploads']);
set('shared_files', ['.env', 'web/.htaccess', 'web/robots.txt']);
set('env_vars', '/usr/bin/env');

task('deploy:stop', function () {
    writeln('Stop deploy... Quick deploy via Laravel Forge branch master');
})->desc('Stop deployment');
before('deploy', 'deploy:stop');

task('pull', function () {
    $actions = [
        "ssh forge@178.62.249.226 'cd {{deploy_path}} && wp db export - --allow-root | gzip' > db.sql.gz",
        "gzip -df db.sql.gz",
        "wp db import db.sql",
        "rm -f db.sql",
        "wp search-replace 'www.tygvaskan.se' 'tygv.app'",
        "wp search-replace 'tygvaskan.se' 'tygv.app'",
        "rsync --exclude .cache -rve ssh " .
        "forge@178.62.249.226:{{deploy_path}}/web/app/uploads web/app",
        "wp rewrite flush",
    ];
    foreach ($actions as $action) {
        writeln("{$action}");
        writeln(runLocally($action));
    }
});