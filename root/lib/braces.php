<?php

function braces($match) {
	static $stack=array();
	$arg = trim($match[2]);
	if(substr($arg,0,1)=='!') {
		$arg = substr($arg,1);
		$not = true;
	} else $not = false;
	
	switch($match[1]) {
	case 'if':
		$areavar = area_exists($arg) || il_set($arg);
		if((!$not && $areavar) || ($not && !$areavar)) {
			array_push($stack,"");
			return "";
		} else {
			array_push($stack,":hide($arg)\x03");
			return "\x02hide($arg):";
		}
	case 'elif':
		if(!$stack) return "{{<b>Unmatched ELIF</b>}}";
		$x = array_pop($stack);
		if($x) {
			if((!$not && isset($areas[$arg])) || ($not && !isset($areas[$arg]))) {
				array_push($stack,"");
				return $x;
			} else {
				array_push($stack,":hide($arg)\x03");
				return "$x\x02hide($arg):";
			}
		} else {
			array_push($stack,":hide($arg)\x03");
			return "\x02hide($arg):";
		}
	case 'else':
		if(!$stack) return "{{<b>Unmatched ELSE</b>}}";
		$x = array_pop($stack);
		if($x) {
			array_push($stack,"");
			return $x;
		} else {
			array_push($stack,":hide(else)\x03");
			return "\x02hide(else):";
		}
	case 'fi':
		if(!$stack) return "{{<b>Unmatched FI</b>}}";
		return array_pop($stack);
	case 'for':
		break;
	case 'rof':
		break;
	case 'var':
		if(($k=strpos($arg,':'))!==false) {
			$parg = substr($arg,0,$k);
			$sarg = substr($arg,$k+1);
			if(is_numeric($sarg)) $sarg=(int)$sarg;
		} else {
			$parg = $arg;
			$sarg = null;
		}
		return $sarg? il_get2($parg,$sarg,"{var:$parg:$sarg}"): il_get($parg,"{var:$parg}");
	case 'set':
		$args = explode(':',$arg);
		if(count($args)>2)
			il_put2($args[0],$args[1],$args[2]);
		else
			il_put($args[0],$args[1]);
		return '';
	case 'area':
		return make_area($arg);
	case 'content':
		return $GLOBALS['content'];
	case 'br':
	case '':
		return "\n";
	default:
		return $match[0];
	}
	return $arg;
}

function unbrace($text) {
	$text = preg_replace_callback('/{(\w+):?([^}]*)}\s*/', "braces", $text);
	while(strpos($text,"\x02")) {
		$text = preg_replace("/\x02[^\x02\x03]*\x03/s",'',$text);
	}
	return $text;
}

function file_unbrace($filename) {
	return unbrace(file_get_contents($filename));
}

function il_sets($match) {
	print_r($match);
	return '<!-- jeje -->';
}

?>