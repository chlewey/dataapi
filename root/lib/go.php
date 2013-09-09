<?php
# Interlecto Content Management System
# ICMS Version 0.5
# go.php | make and send requested page

# MAKE CONTENT
$line = il_get('line');
if(!($content=il_cache_content_get($line))) {
	$content_function = il_get('content_function','make_content');
	$content = function_exists($content_function)?
			$content_function($line):
				"<p>Function <strong>$content_function(<em>\$line</em>)</strong> does not exists.</p>\n".
				"<pre>".print_r($GLOBALS,true)."</pre>\n";
	if(il_get('cachable'))
		il_cache_content_set($line,$content);
}
il_default('title', mb_convert_case($line,MB_CASE_TITLE,'UTF-8'));
il_default('pagetitle', il_get('title'));

# POST CONTENT PARAMS
$temppath = trim(il_get2('path','template','template/default'),'/');
if(il_get('jqueryui')) {
	il_add('headscripts','<link rel=stylesheet href="/'.$temppath.'/jquery.ui/jquery-ui.css">'.chr(10));
	il_add('headscripts','<script src="/js/jquery-1.9.1.min.js"></script>'.chr(10));
	il_add('headscripts','<script src="/js/jquery-ui.min.js"></script>'.chr(10));
	il_add('scripts','<script src="/'.$temppath.'/defaults.js"></script>'.chr(10));
} elseif(il_get('jquery')) {
	il_add('headscripts','<script src="/js/jquery-1.9.1.min.js"></script>'.chr(10));
}
il_add('headscripts','<!--[if lt IE 9]>'.chr(10));
il_add('headscripts','  <script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>'.chr(10));
il_add('headscripts','  <script src="http://html5shim.googlecode.com/svn/trunk/html5.js" type="text/javascript"></script>'.chr(10));
il_add('headscripts','<![endif]-->'.chr(10));

# MAKE PAGE
require_once "lib/braces.php";

$tempfile = il_get('templatefile','index.html');
echo unbrace(file_get_contents("$temppath/$tempfile"));

?>