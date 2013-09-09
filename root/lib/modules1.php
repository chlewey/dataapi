<?php

if(!is_dir('mod')) {
	mkdir('mod');
}

$mod_i = array();
$mod_r = array();
$mod_c = array();
$mod_p = array();
$mod_a = array();
$mods = array(0);

function mod_fix_filename($word,$mod='',$rel='') {
	if (substr($word,0,1)!='/')
		$word = empty($mod)? $rel.$word: "/mod/$mod/$word";
	return $word;
}

function read_module_info($mod,$lines) {
	global $mod_i,$mod_r,$mod_c,$mod_p,$mod_a;
	$mod_i[$mod] = array();
	$mod_c[$mod] = array();
	foreach($lines as $line) {
		if(substr($line,0,1)=='!') continue;
		if(substr($line,0,1)=='#') {
			il_add('mods/remarks',trim(substr($line,1)));
			continue;
		}
		$args = preg_split('{[^\w_/.]+}',trim($line));
		switch($xx=array_shift($args)) {
		case '':
			break;
		case 'load': // loads module code that is shared to all other modules, or the cms engine
			foreach($args as $code)
				il_add('mods/loads',mod_fix_filename($code,$mod));
			break;
		case 'path': // adds one or several uricases and assignes them to a class in a module code
			$ccase = count($args)>1? array_pop($args): ':';
			if(($n=strpos($ccase,':'))!==false) {
				$cc1 = substr($ccase,0,$n);
				$cc2 = substr($ccase,1+$n);
				foreach($args as $uri) {
					$code = empty($cc1)? il_clear($uri): $cc1;
					$class = empty($cc2)? il_clear($uri): $cc2;
					il_set('mods/uri_loads',$uri,mod_fix_filename($code,$mod));
					il_set('mods/uri_class',$uri,$class);
				}
			} else {
				foreach($args as $uri) {
					$class = empty($cc2)? il_clear($uri): $cc2;
					il_set('mods/uri_class',$uri,$class);
				}
			}
			break;
		case 'alias': // sets a new cannonical name for a given uri case
			if(count($args)!=2) {
				il_set('mods/aliases',$args[1],$args[0]);
			} else {
				il_add('mods/remarks',"Warning! Bad alias line: '".trim($line)."'");
			}
			break;
		case 'hide': // do not use a given uri case (probably because it will be mimicked
			foreach($args as $case)
				il_add('mods/hides',$case);
			break;
		case 'mimic':
			if(count($args)>1) {
				$a = array_pop($args);
				foreach($args as $b)
					il_set('mods/mimics',$b,$a);
			} else {
				il_add('mods/remarks',"Warning! Bad mimic line: '".trim($line)."'");
			}
			break;
		case 'folder':
		case 'file':
			foreach($args as $fn) {
				$fn = mod_fix_filename($fn,'','data/');
			}
			break;
			
/*			
		case 'include':
			foreach($args as $file)
				$mod_i[$mod][] = $file;
			break;
		case 'require':
			$mod_r[$mod] = array();
			foreach($args as $rmod) {
				$mod_r[$mod][] = $rmod;
			}
			break;
		case 'folder':
		case 'file':
			$f = explode('/',$args[0]);
			if(empty($f[0]))
				array_shift($f);
			else
				array_unshift($f,'data');
			$fn = $xx=='file'? array_pop($f): null;
			$d = ensure_path($f);
			if($fn) {
				if(!file_exists($ffn = $d.$fn))
					file_put_contents($ffn,"# file $fn\n");
				$mod_c[$mod][] = "use: $ffn";
			} else {
				$mod_c[$mod][] = "dir: $d";
			}
			break;
		case 'section':
			if(empty($args)) {
				ensure_path(array('data','content'));
				$mod_p[''] = 'root';
			}
			foreach($args as $sec) {
				$d = substr($sec,0,1)=='/'? ensure_path($sec): ensure_path(array('data','content',$sec));
				$mod_p[$sec] = 'root';
			}
			break;
		case 'alias':
			$pp = array_pop($args);
			foreach($args as $sec) {
				$mod_a[$sec] = $pp;
				if(empty($mod_p[$sec]))
					$mod_p[$sec] = 'alias';
			}
			break;
		case 'make':
			$mk = array_pop($args);
			if(empty($args))
				$mod_p[$mk] = $mk;
			foreach($args as $sec) {
				$mod_p[$sec] = $mk;
			}
			break;
		case 'rem':
			foreach($args as $rmod)
				$mod_c[$mod][] = implode(' ',$args);
			break;
*/
		default:
			il_add('mods/remarks',"Line: '".trim($line)."'");
		}
	}
}

$mod_d = dir('mod');
while(false !== ($mod=$mod_d->read())) {
	if(substr($mod,0,1)=='.') continue;
	if($mod=='modules.php') continue;
	if(is_dir($dir="mod/$mod")) {
		$mods[] = $mod;
		if(file_exists($cfg="$dir/module.ini")) {
			read_module_info($mod,file($cfg));
		} else {
			$dd = dir($dir);
			while(false !== ($file=$dd->read())) {
				$mod_i[$mod] = array();
				$mod_c[$mod] = array();
				if(substr($file,0,1)=='.') continue;
				if(substr($file,-4)=='.php') {
					$mod_i[$mod][] = substr($file,0,-4);
				} elseif(is_dir("$dir/$file")) {
					$mod_c[$mod][] = "subdir '$dir/$file/'";
				} else {
					$mod_c[$mod][] = "a file '$dir/$file/'";
				}
			}
		}
	} else {
		if(!isset($mod_c[0])) $mod_c[0] = array();
		$mod_c[0][] = "rootfile: '$mod'";
	}
}
$mod_d->close();

print_r($mod_r);
#print_r($mod_c);

$mod_x = array();
function commit_module(&$s,$mod) {
	global $mod_x,$mod_i,$mod_c,$mod_r;
	echo "coming to $mod\n";
	print_r($mod_x);
	if(in_array($mod,$mod_x)) return;
	if(isset($mod_r[$mod]))
		foreach($mod_r[$mod] as $req) {
			commit_module($s,$req);
		}
	echo "commiting $mod\n";
	if($mod)
		$s.= "\n# MODULE $mod\n\$il->modules[] = '$mod';\n";
	if(isset($mod_i[$mod]))
		foreach($mod_i[$mod] as $inc) {
			$s.= "require_once 'mod/$mod/$inc.php';\n";
		}
	if(isset($mod_c[$mod]))
		foreach($mod_c[$mod] as $rem) {
			$s.= "# $rem\n";
		}
	if($mod) $mod_x[] = $mod;
}

$s = "<?php\n";
foreach($mods as $mod) {
	echo "<h4>$mod</h4>\n";
	commit_module($s,$mod);
}
$s.= "\n# PATHS\n";
foreach($mod_p as $mod=>$func) {
	$s.= "set_paths('$mod','$func'".(isset($mod_a[$mod])?",'".$mod_a[$mod]."'":'').");\n";
}
$s.= "\n?>";
file_put_contents($modules,$s);

?>