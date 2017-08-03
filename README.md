# PHPWebsiteBackup
Simple website backup script in PHP with interface

Copyright (c) 2017-Present    Guillaume P. (Indigo744)

License: MIT (see LICENSE file)


Graphical interface is based on Encode Explorer by Marek Rei

https://github.com/marekrei/encode-explorer


## Installation
 1. Clone/download repo
 2. Update backup.conf.php
 3. Upload files to your server (it is recommended to use a subdirectory instead of root)
 4. *[Optional, for more security on Apache]* Update ht.access and rename it to .htaccess
 5. *[Optional]* You can also edit some value in index.php
 
## Usage 
### Manual backup / download through GUI
To get the graphical interface, simply run the index.php file: 
``` 
Example:  https://mywebsite.com/backup-dir/index.php
```
Default admin is 

A backup can be started by clicking on `Manual backup` on bottom right.

### Automated backup
Start a backup by calling the backup.php file directly: 
``` 
Example:  https://mywebsite.com/backup-dir/backup.php
```
Start a specific backup using the `n` parameter:
``` 
Example:  https://mywebsite.com/backup-dir/backup.php?n=backup-1
```
Display more information using the `debug` parameter:
``` 
Example:  https://mywebsite.com/backup-dir/backup.php?debug
```




