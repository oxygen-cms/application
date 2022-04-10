<?php

// capture release as git sha
if(file_exists(base_path('REVISION'))) {
    $revision = file_get_contents(base_path('REVISION'));
} else {
    $revision = exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD');
}

return array(
    'dsn' => env('SENTRY_DSN'),

    'release' => 'oxygen@' . trim($revision),

    'breadcrumbs' => [
        // Capture Laravel logs in breadcrumbs
        'logs' => true,

        // Capture SQL queries in breadcrumbs
        'sql_queries' => true,

        // Capture bindings on SQL queries logged in breadcrumbs
        'sql_bindings' => true,

        // Capture queue job information in breadcrumbs
        'queue_info' => true,

        // Capture command information in breadcrumbs
        'command_info' => true,
    ],

);
