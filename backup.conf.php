<?php

/* Little protection to avoid being references by anything else
 */
if(!defined('IN_BACKUP_TOOL'))
    die("Nope");

/* Log management
 */
define('LOGFILENAME', "log.txt");
define('LOG_MAXLINE', "5000");

/* Define if latest backup should be copied over FTP
 */
define('COPY_OVER_FTP', FALSE);

/* Compression method
 * 'zip' or 'tgz' (.tar.gz) 
 * tgz is faster and smaller
 */
define('COMPRESSION_METHOD', "tgz");

/* Remote FTP credentials
 */
const REMOTE_FTP = array(
    "host" => "",
    "user" => "",
    "pass" => ""
);

/* List of backups to perform
 */
const BACKUPS = array(
    // Here is an example
    "backup-1" => array(
        "max_backups" => 30,                    // Nb of backups to keep
        "paths" => array(                           // Array of paths to backup (if this is omitted, no file backup will be performed)
            "/home/user/www/mywebsite"
        ),
        "excludes" => array(                      // List of regex exclude for filenames
            "*/backup/*",
            "*.exe"
        ),
        "sql" => array(                              // SQL database to backup (if this is omitted, no SQL backup will be performed)
            "host" => "",                              // SQL DB host (or IP)
            "dbase" => "",                            // SQL DB name
            "user" => "",                              // SQL DB user
            "set" => "utf8"                           // SQL DB set
        )
    )
);

