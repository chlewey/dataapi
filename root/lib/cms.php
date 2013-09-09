<?php
# Interlecto Content Management System
# ICMS Version 0.5
# cms.php | ICMS engine

require_once "lib/functions.php";
require_once "lib/il.php";
require_once "lib/line.php";

if(file_exists($modules='mod/modules.php') && empty($_GET['reset_modules'])) {
	require_once $modules;
} else {
	require_once 'lib/modules.php';
	require_once $modules;
}

require_once "lib/areas.php";

?>