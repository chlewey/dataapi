<?php
//$debuging = true;
define('DATALOG_WWW','/var/www/vhosts/orugaamarilla/root/data/datalog/');
require_once 'lib/datalog.php';
$api = new datalog();
$api->go();
$api->close();
?>
