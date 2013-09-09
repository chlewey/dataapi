<?php

function il_line() {
	if(il_set('line')) return il_get('line');
	if(isset($_REQUEST['line'])) {
		$line=$_REQUEST['line'];
	} else {
		$line = is_prefix($_SERVER['SCRIPT_NAME'],$_SERVER['REQUEST_URI'])? '': (
			isset($_SERVER['REDIRECT_URL'])? substr($_SERVER['REDIRECT_URL'],IL_IGNORE):
				substr($_SERVER['REQUEST_URI'],IL_IGNORE)
			);
	}
	il_put('line',$line);
	return $line;
}

function il_line_precheck($redirect=true) {
	$line = il_line();
	if(($cannon = str2uri($line)) != $line)
		if($redirect)
			redirect("/$cannon");
		else
			echo "--> '$line' :: '$cannon' <!--";
}

function il_line_struct() {
	global $il;
	$line = il_line();
	$pre = '';
	$nom = '';
	$fun = 'make_root';
	il_line_put('cannon',$line);
	
	$coline = $line;
	foreach($il->paths as $p=>$g) {
		if($p==$line) {
			$pre = $p;
			$coline = '';
			if(is_array($g)) {
				$nom = $g[1];
				$fun = 'make_'.$il->paths[$g[1]];
			}
			else {
				$nom = $p;
				$fun = 'make_'.$g;
			}
			break;
		}
		if(is_prefix("$p/",$line)) {
			if(strlen($pre)>strlen($p)) continue;
			$pre = $p;
			$coline = substr($line,strlen($pre)+1);
			if(is_array($g)) {
				$nom = $g[1];
				$fun = 'make_'.$il->paths[$g[1]];
			}
			else {
				$nom = $p;
				$fun = 'make_'.$g;
			}
		}
	}
	$line = $coline;

	il_put('content_function',$fun);

	il_line_put('line',$line);
	il_line_put('prefix',$pre);
	il_line_put('nominal',$nom);
	il_line_put('maker',$fun);

	if(empty($line)) $line='index';

	preg_match('{((([\w-./]*)/)?([\w-]*\w))(\.(\w+))?($)}',$line,$pp);
	if(count($pp)<6)
		preg_match('{((([\w-./]*)/)?())(())?($)}',$line,$pp);
	il_line_put('extension',$pp[6]);
	il_line_put('last',$pp[4]);
	il_line_put('dashed',$dashed = str_replace('/','-',empty($pp[3])?$pp[4]:(empty($pp[4])?$pp[3]:$pp[1])));
	il_line_put('spaced',str_replace(array('-','_'),' ',$dashed));
	il_line_put('path',explode('/',$pp[3]));

	il_line_put('matches',$pp);

	il_put('line',$line);
}

function il_line_get($key,$def=null,$oe=false) { return il_get2('line',$key,$def,$oe); }
function il_line_set($key,$def=null) { return il_set2('line',$key,$def); }
function il_line_put($key,$val) { il_put2('line',$key,$val); }

?>