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

function read_module_info($mod,$lines) {
	global $mod_i,$mod_r,$mod_c,$mod_p,$mod_a;
	$mod_i[$mod] = array();
	$mod_c[$mod] = array();
	foreach($lines as $line) {
		if(substr($line,0,1)=='!') continue;
		if(substr($line,0,1)=='#') {
			$mod_c[$mod][] = trim(substr($line,1));
			continue;
		}
		$inst = preg_split('{[^\w_/.]+}',trim($line));
		switch($xx=array_shift($inst)) {
		case '':
			break;
		case 'include':
			foreach($inst as $file)
				$mod_i[$mod][] = $file;
			break;
		case 'require':
			$mod_r[$mod] = array();
			foreach($inst as $rmod) {
				$mod_r[$mod][] = $rmod;
			}
			break;
		case 'folder':
		case 'file':
			$f = explode('/',$inst[0]);
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
			if(empty($inst)) {
				ensure_path(array('data','content'));
				$mod_p[''] = 'root';
			}
			foreach($inst as $sec) {
				$d = substr($sec,0,1)=='/'? ensure_path($sec): ensure_path(array('data','content',$sec));
				$mod_p[$sec] = 'root';
			}
			break;
		case 'alias':
			$pp = array_pop($inst);
			foreach($inst as $sec) {
				$mod_a[$sec] = $pp;
				if(empty($mod_p[$sec]))
					$mod_p[$sec] = 'alias';
			}
			break;
		case 'make':
			$mk = array_pop($inst);
			if(empty($inst))
				$mod_p[$mk] = $mk;
			foreach($inst as $sec) {
				$mod_p[$sec] = $mk;
			}
			break;
		case 'rem':
			foreach($inst as $rmod)
				$mod_c[$mod][] = implode(' ',$inst);
			break;
		default:
			$ll = implode(' ',$inst);
			$mod_c[$mod][] = "line: '$xx $ll'";
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