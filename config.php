<?php
$config = parse_ini_file("config.ini");
if ($config === false) {
  syslog(LOG_EMERG, "Configuration file for graf backend not found, aborting");
  exit(1);
}
?>
