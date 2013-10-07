<?php
require_once "lib/api.php";
$api = new api('status','0.1');
$l = isset($_SERVER['REDIRECT_URL'])? $_SERVER['REDIRECT_URL']:(
	isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI']: '');
if(preg_match('{/?status/(\d{3})\b}',$l,$m))
	$api->status($m[1]);
else
	$api->status(404,'Request has no sense');
$api->close();
?>
