<html>
<body>
<?php

define('DEBUG', isset($_GET['debug']));

define('IN_BACKUP_TOOL', 'YES');

require_once("backup.conf.php");

if (DEBUG) {
    error_reporting(E_ALL|E_STRICT);
    ini_set('display_errors',1);
}

function main()
{
    require_once("backup.conf.php");
    
    if ($backup_name = filter_input(INPUT_GET, 'n', FILTER_SANITIZE_STRING)) {
        // Filter for keeping only this backup name
        $backup_listing = array_intersect_key(BACKUPS, array($backup_name => ""));
    } else {
        // All backup!
        $backup_listing = BACKUPS;
    }
    
    echo "<pre>\n";
    
    $current_timestamp = date('Ymd_His');
    log_msg("Starting backup process");
    log_msg("%d site(s) to backup", sizeof($backup_listing));
    
    $time_start = microtime(true); 
    foreach($backup_listing as $backup_name => $backup)
    {
        $time_lap = microtime(true); 
        
        log_msg("[%s] Start processing", $backup_name);
        
        @mkdir($backup_name);
        
        if (isset($backup['paths'])) 
        {
            $paths = $backup['paths'];
        } else {
            $paths = array();
        }
        
        //
        // SQL Dump
        //
        if (isset($backup['sql'])) 
        {
            log_msg("[%s] Start SQL backup on %s (database %s)", $backup_name, $backup['sql']['host'], $backup['sql']['dbase']);
            
            $backup_db_filename = "db_{$backup['sql']['dbase']}_{$current_timestamp}.sql";
            
            $size = dump_sql($backup['sql']['host'], $backup['sql']['user'], $backup['sql']['pass'], $backup['sql']['set'], $backup['sql']['dbase'], $backup_db_filename);
            
            if ($size === FALSE) 
            {
                log_msg("[%s] File %s is empty!", $backup_name, $backup_db_filename);
            } else {
                log_msg("[%s] File %s generated (%s)", $backup_name, $backup_db_filename, human_filesize($size));
                
                // Add SQL dump to list of path to be included in backup
                array_push($paths, $backup_db_filename);
            }
        }
        
        //
        // FILE BACKUP
        //
        if (count($paths) > 0) 
        {
            // extension depends on COMPRESSION_METHOD
            $backup_filename = "{$backup_name}/bkup_{$backup_name}_{$current_timestamp}"; 
            $remote_filename = "bkup_{$backup_name}_latest"; 
            switch(COMPRESSION_METHOD){
                case 'zip': 
                    $backup_filename .= ".zip";
                    $remote_filename .= ".zip";
                    break;
                default:
                case 'tgz':
                    $backup_filename .= ".tar.gz";
                    $remote_filename .= ".tar.gz";
                    break;
            }
            
            $size = compress($paths, $backup_filename, $backup['excludes'], COMPRESSION_METHOD);
            
            if ($size === FALSE) 
            {
                log_msg("[%s] Error while compressing to %s", $backup_name, $backup_filename);
            } else {
                log_msg("[%s] File %s generated (%s)", $backup_name, $backup_filename, human_filesize($size));
                
				if (COPY_OVER_FTP) {
					if (send_backup_over_ftp($backup_filename, $remote_filename))
					{
						log_msg("[%s] File sent over FTP done (%s)", $backup_name, $remote_filename);
					} else {
						log_msg("[%s] File sent over FTP failed!", $backup_name);
					}
				}
            }
        }
        
        //
        // CLEANING
        //
        if (isset($backup_db_filename)) @unlink($backup_db_filename);
        
        $backup_nb = trim_nb_of_backups($backup_name, $backup['max_backups']);
        if ($backup_nb === FALSE) {
            log_msg("[%s] Error while trying to truncate number of backups", $backup_name);
        } else {
            log_msg("[%s] %d/%d backups available", $backup_name, $backup_nb, $backup['max_backups']);
        }
        
        log_msg("[%s] Done in %f seconds", $backup_name, (microtime(true) - $time_lap));
        
    }
    
    $lines = trim_log_file();
    log_msg("%d lines in log", $lines);
    
    log_msg("Total Execution Time: %f seconds", (microtime(true) - $time_start));

    echo "</pre>\n";
}

function dump_sql($host, $user, $pass, $set, $dbase, $output_file) 
{
    $command = "mysqldump -c -h{$host} -u{$user} -p{$pass} --default-character-set={$set} -N {$dbase} > {$output_file}";
    
    if (DEBUG) log_msg($command);
    $output = shell_exec($command);
    if (DEBUG) log_msg("Output: \n%s\n-----", $output);
    
    return @filesize($output_file);
}

function compress($paths, $output_file, $excludes, $method="tgz")
{
    $command = "";
    if ($method == "tgz") 
    {
        $command = "tar -czf '{$output_file}' ";
        $command .= implode(" ", $paths);
        if (count($excludes) > 0) {
            $command .= " --exclude='" . implode("' --exclude='", $excludes) . "'";
        }
    } else if ($method == "zip") 
    {
        $command = "zip -r9 '{$output_file}' ";
        $command .= implode(" ", $paths);
        if (count($excludes) > 0) {
            $command .= " -x " . implode(" ", $excludes);
        }
    } else {
        return FALSE;
    }
    
    if (DEBUG) log_msg($command);
    $output = shell_exec($command);
    if (DEBUG) log_msg("Output: \n%s\n-----", $output);
    
    return @filesize($output_file);
}

function send_backup_over_ftp($local_path, $remote_name)
{
    // Build command
    $command = sprintf("curl -v -T %s ftp://%s/%s --user %s:%s 2>&1", 
                       $local_path, REMOTE_FTP['host'], $remote_name,
                       REMOTE_FTP['user'], REMOTE_FTP['pass']);
    if (DEBUG) log_msg($command);
    // Send command
    $return_val = -1;
    $out = array();
    exec($command, $out, $return_val);
    $out = implode("\n", $out);
    if (DEBUG) log_msg("Output ($return_val): \n%s\n-----", $out);
    
    return ($return_val === 0);
}

function scandir_nodot($path, $sort)
{
    return array_diff(scandir($path, $sort), array('..', '.'));
}

function trim_nb_of_backups($backup_path, $max_nb_of_backups) 
{
    // Get list of files
    $files = scandir_nodot($backup_path, SCANDIR_SORT_DESCENDING);
    $i = 0;
    while(count($files) > $max_nb_of_backups)
    {
        // Pop oldest entry and delete it
        unlink($backup_path . '/' . array_pop($files));
        // Check if we arent in infinite loop
        if ($i++ > $max_nb_of_backups * 100) {
            return FALSE;
        }
        // Get new list of files
        $files = scandir_nodot($backup_path, SCANDIR_SORT_DESCENDING);
    }
    return count($files);
}

function human_filesize($bytes, $decimals = 2) {
  $sz = 'BKMGTP';
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

function nb_of_lines($filename)
{
    return intval(exec("wc -l '$filename'"));
}

function trim_log_file()
{
    $number_of_lines = nb_of_lines(LOGFILENAME);
    if (DEBUG) log_msg("Before: %d lines", $number_of_lines);
    if ($number_of_lines > LOG_MAXLINE) 
    {
        $tmp_file = LOGFILENAME . ".tmp";
        shell_exec(sprintf("tail -n %d '%s' > '%s'", LOG_MAXLINE, LOGFILENAME, $tmp_file));
        shell_exec(sprintf("mv '%s' '%s'", $tmp_file, LOGFILENAME));
    }
    
    return nb_of_lines(LOGFILENAME);
}

// log_msg function
// Can be used like sprintf: log_msg("text", args...)
// No params defined, as we use func_get_args() 
function log_msg()
{
    $args = func_get_args();
    if (count($args) == 0) return false;
    // Get first string and apply args if needed
    $message = array_shift($args);
    if (count($args) > 0) $message = vsprintf($message, $args);
    
    $log_msg = sprintf("[%s] %s\n", date("d/M/Y-H:i:s"), $message);
    
    echo $log_msg;
    
    file_put_contents(LOGFILENAME, $log_msg, FILE_APPEND | LOCK_EX);
}

main();

?>
</body>
</html>