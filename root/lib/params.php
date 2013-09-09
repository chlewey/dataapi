<?php
# Interlecto Content Management System
# ICMS Version 0.5
# params.php | get user request and session options

session_start();
$today = time();
$nx = $today + 7*24*60*60;

#################
# Open database #
#################
il_open_db();

#######################################
# Check if there is a registered user #
#######################################
if(module_exists('users')) {
	# check if there is a requested user
	if(isset($_REQUEST['login'])) {
		if(confirm_user($user=$_REQUEST['user'],$_REQUEST['password'])) {
			set_user($user,!empty($_REQUEST['remember']));
		}
	}
	# check if there is a current session
	elseif(isset($_SESSION['user'])) {
		set_user($_SESSION['user'],!empty($_SESSION['user_remember']));
	}
	# check if there is a cookied session
	elseif(isset($_COOKIE['user'])) {
		# if the session has already expired (or was set as to not remember)
		if(empty($_COOKIE['user_remember']) || ($ux=(int)$_COOKIE['user_expires'])>$today) {
			$_SESSION['def_user'] = $_COOKIE['user'];
		}
		# the session has been set to be remembered and has not expired
		else set_user($_COOKIE['user'],true);
	}
}

###############################
# Stablish requested resource #
###############################
$line = il_line();
if(il_empty('user'))
	il_cache_check($line);
il_line_precheck();
il_line_struct();

##########################
# Reset Global Variables #
##########################
if(!isset($_IL)) $_IL=array();
foreach($GLOBALS as $var=>$val) {
	if(substr($var,0,3) == 'il_')
		$il->setkey(substr($var,3),$val);
	if($var=='GLOBALS') continue;
	if($var=='il') continue;
	if(substr($var,0,1) != '_')
		unset($GLOBALS[$var]);
}
unset($val);
?>
