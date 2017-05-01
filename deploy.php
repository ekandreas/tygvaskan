<?php
namespace Deployer;

require __DIR__ . "/vendor/deployer/deployer/recipe/common.php";

set('ssh_type', 'native');
set('ssh_multiplexing', true);

set('domain', 'tygvaskan.app');

server('dev','localhost');

server('production', 'elseif.se', 22)
    ->set('deploy_path', '~/www.tygvaskan.se')
    ->user('forge')
    ->set('branch', 'develop')
    ->set('database', 'tygvaskan')
    ->stage('production')
    ->identityFile();

set('repository', 'https://github.com/ekandreas/tygvaskan.git');

set('env', 'prod');
set('keep_releases', 2);
set('shared_dirs', ['web/app/uploads']);
set('shared_files', ['.env', 'web/.htaccess', 'web/robots.txt']);
set('env_vars', '/usr/bin/env');

task('pull', function () {
    $actions = [
        "ssh forge@elseif.se 'cd {{deploy_path}} && wp db export - --allow-root | gzip' > db.sql.gz",
        "gzip -df db.sql.gz",
        "wp db import db.sql",
        "rm -f db.sql",
        "wp search-replace 'www.tygvaskan.se' 'tygvaskan.app'",
        "wp search-replace 'tygvaskan.se' 'tygvaskan.app'",
        "wp search-replace 'https://tygvaskan' 'http://tygvaskan'",
        "rsync --exclude .cache -rve ssh " .
        "forge@elseif.se:{{deploy_path}}/web/app/uploads web/app",
        "wp rewrite flush",
    ];

    foreach ($actions as $action) {
        writeln("{$action}");
        writeln(runLocally($action, 999));
    }
});
