<?php
#sqlite
#define("DBPATH", "db/data.db");
#define("PDO_DSN", "sqlite:".DBPATH);

#mysql
define("MYSQL_DB", "eaps");
define("MYSQL_HOST", "localhost");
define("MYSQL_USER", "eaps");
define("MYSQL_PASS", "ekKefmafEic8");
define("PDO_DSN", "mysql:host=".MYSQL_HOST.";dbname=".MYSQL_DB);

define("DB_DRIVER", "mysql");

define("DEBUG_2",4);
define("DEBUG",3);
define("VERBOSE",2);
define("INFO",1);

define("LOG_LEVEL",INFO);
?>
