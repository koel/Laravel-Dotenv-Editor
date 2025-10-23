<?php

return [
    /*
    |----------------------------------------------------------------------
    | Auto backup mode
    |----------------------------------------------------------------------
    |
    | This value is used when you save your file content. If value is true,
    | the original file will be backed up before save.
    */

    'autoBackup' => true,

    /*
    |----------------------------------------------------------------------
    | Backup location
    |----------------------------------------------------------------------
    |
    | This value is used when you backup your file. This value is the sub
    | path from root folder of project application.
    */

    'backupPath' => base_path('storage/dotenv-editor/backups/'),

    /*
    |----------------------------------------------------------------------
    | Always create backup folder
    |----------------------------------------------------------------------
    |
    | If this setting is set to true, the backup folder set up in the
    | 'backupPath' setting will always be created regardless of whether the
    | backup is performed or not.
    */

    'alwaysCreateBackupFolder' => false,


    /*
    |----------------------------------------------------------------------
    | Writer settings
    |----------------------------------------------------------------------
    */
    'writer' => [
        /*
        |----------------------------------------------------------------------
        | End of Line mode
        |----------------------------------------------------------------------
        |
        | 'os' (default) - use PHP_EOL as line separator
        | 'windows' - use '\r\n' as line separator
        | 'unix' - use '\n' as line separator
        */
        'EOLMode' => 'os',

        /*
        |----------------------------------------------------------------------
        | End file with line-break
        |----------------------------------------------------------------------
        |
        | true (default) - add EOL symbol at end of file
        | false - don't add EOL symbol at end of file
        */
        'endsWithLinebreak' => true,
    ],
];
