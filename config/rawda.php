<?php

/*
| Settings of the mosque system that belong to the machine it runs on,
| not to the mosque (those are in the settings table).
*/

return [

    /*
    | The folder the files the admin uploads are kept in (their own slide
    | template). Empty means storage/app/private. The demo sets a folder of
    | its own, so that trying things there never changes the real files.
    */
    'files_path' => env('RAWDA_FILES_PATH'),

    'demo' => [

        /*
        | The demo (demo.bat) is the system on invented data, in a database
        | of its own whose name ends with "_demo". It is served at this
        | port, and whatever answers there must be the demo: a system that
        | holds another database refuses every request that reaches it on
        | this port. demo.bat names the same port.
        */
        'port' => 8010,

    ],

    'backups' => [

        /*
        | The folder the backup files are kept in. By default it is the
        | "backups" folder next to start.bat. Copy its newest file to a USB
        | stick from time to time: a backup on the same disk does not
        | survive the loss of that disk.
        */
        'path' => env('RAWDA_BACKUPS_PATH', base_path('backups')),

        /*
        | An automatic backup is taken the first time the system is used
        | each day; this many of them are kept. Backups the admin takes by
        | hand are kept until the admin deletes them.
        */
        'keep' => (int) env('RAWDA_BACKUPS_KEEP', 30),

        'automatic' => (bool) env('RAWDA_BACKUPS_AUTOMATIC', true),

    ],

];
