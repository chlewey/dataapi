<?php
function is_prefix($a,$b) {
	return substr($b,0,strlen($a))==$a;
}

function ensure_path($path) {
	if(is_array($path)) {
		$d = '';
		foreach($path as $sd) {
			$d.= $sd.'/';
			if(!is_dir($d)) mkdir($d);
		}
	} else {
		$d = "$path";
		if(!is_dir($d)) mkdir($d);
	}
	return $d;
}

function ensure_file($filepath) {
	if(is_array($filepath)) {
		$fn = array_pop($filepath);
		$d = ensure_path($filepath);
	} else {
		$path = explode('/',"$filepath");
		$fn = array_pop($path);
		$d = ensure_path($path);
	}
	$cfn = $d.$fn;
	file_put_contents($cfn,"#$fn\n");
	return $cfn;
}

function listdir($path,$ordered=0,$recursive=false,$rlevel=10) {
	if(!is_dir($path))
		return Null;
	$r = array();
	$d = dir($path);
	while(false!==($e=$d->read())) {
		$pe = "$path/$e";
		$r[$e]=array($pe,substr($e,0,1)=='.',is_dir($pe));
		if($recursive && $rlevel>0 && !$r[$e][1] && $r[$e][2])
			$r[$e][3] = listdir($pe,$ordered,true,$rlevel-1);
	}
	if($ordered>0) ksort($r);
	elseif($ordered<0) krsort($r);
	return $r;
}

function str2uri($str,$esp='-') {
	//echo $str;
	return preg_replace(
		array(
			'{\s+|\xc2\xa0}',
			'{\xc2\xa3}',
			'{\xc2\xa7}',
			'{\xc2\xad}',
			'{\xc2\xae}',
			'{\xc2\xb2}',
			'{\xc2\xb3}',
			'{\xc2\xb6}',
			'{\xc2\xb9}',
			'{\xc3\x9f}',
			'{\xc3[\xa0-\xa5]|\xc2\xaa}',
			'{\xc3\xa6}',
			'{\xc3\xa7|\xc2[\xa2\xa9]}',
			'{\xc3[\xa8-\xab]}',
			'{\xc3[\xac-\xaf]}',
			'{\xc3\xb0}',
			'{\xc3\xb1}',
			'{\xc3[\xb2-\xb6\xb8]|\xa2\xba}',
			'{\xc3[\xb9-\xbc]|\xc2\xb5}',
			'{\xc3[\xbd\xbf]|\xc2\xa5}',
			'{\xc3\xbe}',
			'{\xc2[\xa1\xbf]}',
			'{[\xc4\xdf][\x91\xbf]}',
			'{[\xe0\xef][\x91\xbf][\x91\xbf]}',
			'{[\xf0\xf7][\x91\xbf][\x91\xbf][\x91\xbf]}',
			'{[^-./0-9_a-z]}',
			'{-*_+-*}','{-*/+-*}','{^-+|-+$}','{--+}'
			),
		array(
			$esp,'l','s','','r','2','3','p','1','ss',
			'a','ae','c','e','i',
			'd','n','o','u','y',
			'th','-','-','-','-',
			'-','_','/','','-'
			),
		mb_convert_case($str,MB_CASE_LOWER,'UTF-8'));
}

function dat2array($file,&$ar,$spanned=false) {
	$f=file($file);
	$ar[$g="status"]='';
	foreach($f as $l) {
		if(($p=strpos($l,':'))!==false) {
			$ar[$g = trim(substr($l,0,$p))] = str_replace('\x3a',':',trim(substr($l,$p+1)));
		} else {
			if($g=='status' && $ar[$g]) $ar[$g='text']='';
			$ar[$g].="\n".str_replace('\x3a',':',trim($l));
		}
	}
	if(!isset($ar['text'])) $ar['text']="";
	elseif($spanned) $ar['text'] = "<span>".str_replace("\n","</span>\n<span>",trim($ar['text']))."</span>";
}

function array2dat($file,$ar,$order=array('status'),$deny=array('line')) {
	$t = "";
	foreach($order as $key)
		if(isset($ar[$key])&&($val=trim($ar[$key]))!=='')
			if(strrpos($val,"\n")) {
				$val = str_replace(':','\x3a',$val);
				$t.="$key:\n$val\n";
			}
			else $t.="$key:$val\n";
	foreach($ar as $key=>$val)
		if(!in_array($key,$order) && !in_array($key,$deny))
			if(strrpos($val,"\n")) {
				$val = str_replace(':','\x3a',$val);
				$t.="$key:\n$val\n";
			}
			else $t.="$key:$val\n";
	$fp = fopen($file,'w');
	fwrite($fp, $t);
	fclose($fp);
}

function checkorredirect($url,$pattern=null,$transform=false) {
	$cannon = il_line_get('cannon');
	if(substr($url,-2)=='.*')
		$url = substr($url,0,-1).il_line_get('extension','html',true);
	if($pattern) {
		if(!preg_match($pattern,$cannon))
			redirect($transform? preg_replace($transform,$url,$cannon): $url);
	} else {
		if(ltrim($cannon,'/') != ltrim($url,'/'))
			redirect($url);
	}
}
// alias for http_redirect. Allows to comment out the actual redirect for debugging processes
function redirect($url, $params=null, $session=false, $status=0) {
	if(!$params) $params=array();
	if(function_exists('http_redirect')) return http_redirect($url,$params,$session,$status);
	while(@ob_end_clean());
	if(preg_match('{^\w+://}',$url)) {
		$fullurl = $url;
	} else {
		$protocol = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']!='on' ? 'http://': 'https://';
		$host = $_SERVER['HTTP_HOST'];
		if(substr($url,0,1)=='/') {
			$fullurl = $protocol.$host.$url;
		} else {
			$dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
			$cpi = pathinfo(il_get2('line','cannon'));
			$dir = (isset($cpi['dirname']) || $cpi['dirname']='.' || $cpi['dirname']='/' || $cpi['dirname'] = '\\')?
				'/': '/'.$cpi['dirname'].'/';
			$fullurl = $protocol.$host.$dir.$url;
		}
	}
	header("Location: $fullurl");
	die( "Redirect to <a href=\"$fullurl\">$url</a> (from ".il_line_get('cannon').")" );
}

function make_section($content,$title=null,$level=2,$title_link=null,$class=null,$id=null) {
	$s = "<section";
	if($class) {
		$s.= " class=";
		if(is_array($class)) $s.='"'.implode(' '.$class).'"';
		else $s.="$class";
	}
	if($id) $s.=" id=$id";
	$s.=">\n";
	if($title) {
		$s.="<h$level>";
		if($title_link) $s.="<a href=\"$title_link\">";
		$s.=$title;
		if($title_link) $s.="</a>";
		$s.="</h$level>\n";
	}
	$s.=$content;
	$s.="\n</section>\n";
	return $s;
}

function json_write($filename,$data,$options=0) {
	if(!file_exists($filename)) ensure_file($filename);
	file_put_contents($filename,json_encode($data,$options));
	return true;
}

function json_read($filename,$default=array()) {
	if(!file_exists($filename)) return $default;
	$r = json_decode(file_get_contents($filename),true);
	return isset($r)? $r: $default;
}

function array_mergeinto(&$array1,$array2) {
	return $array1 = array_merge($array1,$array2);
}

?>
