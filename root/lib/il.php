<?php

define("IL_IGNORE", 1);

class Attributer {
	private $vars=array();

	public function __construct() {
	}

	#functions to check existence of keys
	public function exist($key) {
		return isset($this->vars[$key]);
	}
	public function empt($key) {
		return empty($this->vars[$key]);
	}
	public function is_array($key) {
		return isset($this->vars[$key]) && is_array($this->vars[$key]);
	}

	#functions to set and retrieve values of single keys
	public function get($key, $default=null, $onempty=false) {
		return $onempty?
			(empty($this->vars[$key])? $default: $this->vars[$key]):
			(isset($this->vars[$key])? $this->vars[$key]: $default);
	}
	public function set($key, $value) {
		$this->vars[$key] = $value;
	}
	public function put($key, $value) {
		if(empty($this->vars[$key])) $this->vars[$key] = $value;
	}
	public function add($key, $value, $array=false) {
		if(isset($this->vars[$key])) {
			if(is_array($this->vars[$key])) {
				if(is_array($value))
					array_mergeinto($this->vars[$key],$value);
				else
					array_push($this->vars[$key],$value);
			} elseif(is_string($this->vars[$key]))
				$this->vars[$key].= $value;
			elseif(is_numeric($this->vars[$key]))
				$this->vars[$key]+= $value;
		} else {
			$this->vars[$key] = $array? array($value): $value;
		}
	}

	#functions to create new attributes
	public function newvar($key,$value=null)
									{ $this->set($key,$value); }
	public function newstr($key)	{ $this->set($key,''); }
	public function newnum($key)	{ $this->set($key,0); }
	public function newarr($key)	{ $this->set($key,array()); }

	#destroy attribute
	public function clear($key)	{ unset($this->vars[$key]); }
}

require_once "lib/db.php";
class Site extends Attributer {
	private $arrs=array();
	private $db;

	public $modules=array();
	public $areas=array();
	public $paths=array();

	public function __construct() {
		$template = 'nuevo';
		$this->newvar('template',$template);
		$this->newarr('path', array (
			'root' => "",
			'template' => "/template/$template",
			'js' => "/js",
			));
		$this->newvar('scripts',"\n");
	}

	#GENERAL KEYS

	#functions to check existence of keys
	public function is_array($key) {
		return isset($this->arrs[$key]) || (isset($this->vars[$key]) && is_array($this->vars[$key]));
	}
	public function existt($key,$sub) {
		return isset($this->arrs[$key]) && $this->arrs[$key]->exist($sub);
	}
	public function emptt($key,$sub) {
		return empty($this->arrs[$key]) || $this->arrs[$key]->empt($sub);
	}

	#destroy attribute or array
	public function clearr($key,$sub=null) {
		if($sub) {
			$this->arrs[$key]->clear($sub);
		} else {
			unset($this->arrs[$key]);
		}
	}

	#functions to create new attributes
	public function newarr($key, $value=array()) {
		$this->arrs[$key] = new Attributer;
		foreach($value as $k=>$v)
			$this->arrs[$key]->put($k,$v);
	}
	public function setkey($key, $value) {
		if(is_array($value))
			$this->newarr($key,$value);
		else
			$this->set($key,$value);
	}

	#functions to retrieve values of double keys 
	public function gett($key, $sub, $default=null, $onempty=false) {
		#return isset($this->arrs[$key]) && $this->arrs[$key]->exist($sub) ? $this->arrs[$key]->get($sub): $default;
		return isset($this->arrs[$key])? $this->arrs[$key]->get($sub,$default,$onempty): $default;
	}
	public function sett($key, $sub, $value) {
		if(!isset($this->arrs[$key]))
			$this->arrs[$key] = new Attributer;
		$this->arrs[$key]->set($sub,$value);
	}
	public function putt($key, $sub, $value) {
		if(!isset($this->arrs[$key]))
			$this->arrs[$key] = new Attributer;
		$this->arrs[$key]->put($sub,$value);
	}
	public function addd($key, $sub, $value, $array=false) {
		if(!isset($this->arrs[$key]))
			$this->arrs[$key] = new Attributer;
		$this->arrs[$key]->add($sub,$value,$array);
	}
	public function coppy($key1, $key2) {
		$this->arrs[$key1] = $this->arrs[$key2];
	}

	# SPECIFIC KEYS
	public function set_path($path,$func,$alias=null) {
		$this->paths[$path] = $alias? array($func,$alias): $func;
	}

	public function fix_paths() {
		foreach($this->paths as $path=>$what) {
			if(is_array($what) && $what[0]=='alias')
				$this->paths[$path] = is_array($x=$this->paths[$what[1]])? $x[0]: $x;
		}
		foreach($this->paths as $path=>$what) {
			if(is_array($what) && $what[1]=='hiden')
				unset($this->paths[$path]);
		}
		ksort($this->paths);
	}

	public function open_db() {
		if(isset($this->db)) return;
		$server = $this->gett('db','server','localhost');
		$user = $this->gett('db','user','root');
		$password = $this->gett('db','key','');
		$database = $this->gett('db','database','orugaamarilla');
		$this->db = new db($server,$user,$password,$database);
		if($this->db->connect_errno) die("No se pudo conectar a la base '$database': ".$this->db->connect_error);
	}

	public function db_escape($s) {return $this->db->real_escape_string($s);}
	public function query($query) {
		//il_add('queries',$query);
		//echo $query;
		return $this->db->query($query);
	}
	public function dberror() { return $this->db->error; }

	public function select($table,$columns='*',$where=null,$orderby=null,$desc=false,$limit=100,$limitfrom=0) {
		if(is_array($columns) and count($columns))
			$cd = '`'.implode('`,`',$columns).'`';
		elseif($columns == '*')
			$cd = $columns;
		elseif(is_string($columns)) {
			if(preg_match('{[\w_]+}',$columns))
				$cd = "`$columns`";
			else
				$cd = "'".$this->db->real_escape_string($columns)."'";
		} else $cd = '*';
		$query = "SELECT $cd";
		$cleant = $this->db->real_escape_string($table);
		$query.= " FROM `$cleant`";
		if(is_array($where) && count($where)) {
			$w = array();
			foreach($where as $key=>$val) {
				if(is_array($val)) {
					$ss = array();
					foreach($val as $v) $ss[] = $this->db_escape($v);
					$set = "'".implode("','",$ss)."'";
					$w[] = "`$key` IN ($set)";
					continue;
				}
				$eval = $this->db->real_escape_string(substr($val,1));
				switch($c = substr($val,0,1)) {
				case '=': case '<': case '>':
					$w[] = "`$key` $c '$eval'";
					break;
				case '!':
					$w[] = "`$key` <> '$eval'";
					break;
				case '(':
					$ss = explode(',',trim($val,'()'));
					$set = "'".implode("','",$ss)."'";
					$w[] = "`$key` IN ($set)";
					break;
				default:
					$eval = $this->db->real_escape_string($val);
					$w[] = "`$key` = '$eval'";
				}
			}
			$query.= ' WHERE '.implode(' AND ',$w);
		} elseif(is_string($where)) {
			$query.= " WHERE $where";
		}
		if(is_array($orderby) and count($orderby))
			$query.= ' ORDER BY `'.implode('`,`',$orderby).'`'.($desc?' DESC':'');
		elseif(is_string($orderby)) {
			$cleanob = $this->db->real_escape_string($orderby);
			$query.= " ORDER BY `$cleanob`".($desc?' DESC':'');
		}
		if(!empty($limit)) {
			$query.= " LIMIT ".((int)$limitfrom).", ".((int)$limit);
		}

		$query.= ";\n";
		$a = array();
		if(($r = $this->query($query))!==false) {
			while($aa = $r->fetch_array(MYSQLI_ASSOC)) $a[] = $aa;
			$r->free();
			return $a;
		}
		return false;
	}

	public function update($table,$changes,$where=null) {
		$cleant = $this->db->real_escape_string($table);
		$query = "UPDATE `$cleant`";
		$sets = array();
		foreach($changes as $key=>$value)
			$sets.= "`".$this->db->real_escape_string($key)."`='".$this->db->real_escape_string($value)."'";
		if(count($sets))
			$query.= " SET ".implode(', ',$sets);
		if(is_array($where) && count($where)) {
			$w = array();
			foreach($where as $key=>$val) {
				$eval = $this->db->real_escape_string(substr($val,1));
				switch($c = substr($val,0,1)) {
				case '=': case '<': case '>':
					$w[] = "`$key` $c '$eval'";
					break;
				default:
					$eval = $this->db->real_escape_string($val);
					$w[] = "`$key` = '$eval'";
				}
			}
			$query.= ' WHERE '.implode(' AND ',$w);
		} elseif(is_string($where)) {
			$query.= " WHERE $where";
		}
		$query.= ";\n";
		return $this->query($query);
	}
	
	public function close_db() {
		$this->db->close();
	}
};

$il = new Site;

# ALIASING FUNCIONS
function il_empty($key) { return $GLOBALS['il']->empt($key); }
function il_set($key) { return $GLOBALS['il']->exist($key); }
function il_get($key,$def=null,$oe=false) { return $GLOBALS['il']->get($key,$def,$oe); }
function il_put($key,$val) { $GLOBALS['il']->set($key,$val); }
function il_default($key,$val) { $GLOBALS['il']->put($key,$val); }
function il_add($key,$val,$array=false) { $GLOBALS['il']->add($key,$val,$array); }

function il_set2($key,$sub) { return $GLOBALS['il']->existt($key,$sub); }
function il_get2($key,$sub,$def=null,$oe=false) { return $GLOBALS['il']->gett($key,$sub,$def,$oe); }
function il_put2($key,$sub,$val) { $GLOBALS['il']->sett($key,$sub,$val); }
function il_add2($key,$sub,$val,$array=false) { $GLOBALS['il']->addd($key,$sub,$val,$array); }
function il_copy2($key1,$key2) { $GLOBALS['il']->coppy($key1,$key2); }

function set_paths($path,$func,$alias=null) { $GLOBALS['il']->set_path($path,$func,$alias); }
function fix_paths() { $GLOBALS['il']->fix_paths(); }

function module_exists($mod) { return in_array($mod,$GLOBALS['il']->modules); }

function il_open_db() { $GLOBALS['il']->open_db(); }
function il_close_db() { $GLOBALS['il']->close_db(); }
function il_query($query) { return $GLOBALS['il']->query($query); }
function il_select($table,$columns='*',$where=null,$orderby=null,$desc=false,$limit=100,$limitfrom=0) { return $GLOBALS['il']->select($table,$columns,$where,$orderby,$desc,$limit,$limitfrom); }
function il_escape($s) { return  $GLOBALS['il']->db_escape($s); }
function il_dberror() { return  $GLOBALS['il']->dberror(); }
function il_supdate($table,$changes,$where=null) { return $GLOBALS['il']->update($table,$changes,$where); }

function il_avatar($n,$size=null,$default=null) {
	$n=(int)$n;
	if(!$n) return $default;
	if($av=il_get2('avatar',$n)) return empty($size)? $av: (empty($av[$size])? $default: $av['basedir'].$av[$size]);
	$r = il_select('avatar','*',array('idx'=>"=$n"));
	if($r) {
		il_put2('avatar',$n,$av=$r[0]);
		return empty($size)? $av: (empty($av[$size])? $default: $av['basedir'].$av[$size]);
	}
	return $default;
}
function il_banner($n,$size=null,$default=null) {
	$n=(int)$n;
	if(!$n) return $default;
	if($bn=il_get2('banner',$n)) return empty($size)? $bn: (empty($bn[$size])? $default: $bn['basedir'].$bn[$size]);
	$r = il_select('banner','*',array('idx'=>"=$n"));
	if($r) {
		il_put2('banner',$n,$bn=$r[0]);
		return empty($size)? $bn: (empty($bn[$size])? $default: $bn['basedir'].$bn[$size]);
	}
	return $default;
}
/*
*/
?>
