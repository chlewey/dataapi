<?php

define('SET_EMPTY',0);
define('SET_REPLACE',1);
define('SET_ADD',2);
define('GET_EMPTY',0);
define('GET_UNSET',1);

function abconcat($a,$b) {
	return strtolower($a).'/'.str_replace('_',' ',strtolower($b));
}
class area {
	function get($param,$default=null,$how=GET_UNSET) {
		if(($n=strpos($param,'/'))!==false) {
			$p1 = substr($param,0,$n);
			$p2 = substr($param,1+$n);
			if(method_exists($this,$m="{$p1}_get")) return $this->$m($p2,$default,$how);
			if(method_exists($this,$p1)) return $this->$p1($p2);
			if(!isset($this->$p1) || !is_array($this->$p1)) return $default;
			$x = $this->$p1;
			if(!isset($x[$p2])) return $default;
			if(!empty($x[$p2])) return $x[$p2];
			return $how==GET_UNSET? $x[$p2]: $default;
		} else {
			if(method_exists($this,$m="{$param}_get")) return $this->$m($default,$how);
			if(method_exists($this,$param)) return $this->$param();
			if(!isset($this->$param)) return $default;
			if(!empty($this->$param)) return $this->$param;
			return $how==GET_UNSET? $this->$param: $default;
		}
	}
	function set($param,$value,$how=SET_REPLACE) {
		if(($n=strpos($param,'/'))!==false) {
			$p1 = substr($param,0,$n);
			$p2 = substr($param,1+$n);
			if(method_exists($this,$m="{$p1}_set")) $this->$m($p2,$value,$how);
			elseif(method_exists($this,$p1)) $this->$param($p2,$value,$how);
			else {
				if(!isset($this->$p1)) $this->$p1 = array();
				switch($how) {
				case SET_ADD:
					if(!isset($this->$p1[$p2])) $this->$p1[$p2] = array();
					array_push($this->$p1[$p2], $value);
					break;
				case SET_EMPTY:
					if(!empty($this->$p1[$p2])) return;
				default:
					$this->$p1 = array_merge($this->$p1,array($p2=>$value));
				}
			}
		} else {
			if(method_exists($this,$m="{$param}_set")) $this->$m($value,$how);
			elseif(method_exists($this,$param)) $this->$param($value,$how);
			else {
				switch($how) {
				case SET_ADD:
					if(!isset($this->$param)) $this->$param = array();
					array_push($this->$param, $value);
					break;
				case SET_EMPTY:
					if(!empty($this->$param)) return;
				default:
					$this->$param = $value;
				}
			}
		}
	}
	function write($x,$nl=true) {
		if(!isset($this->content)) $this->content = "$x";
		else $this->content.= "$x";
		if($nl) $this->content.= chr(10);
	}
	function finish() {
		$content = isset($this->content)? $this->content: '';
		$format = $this->get('format','html');
		if(is_array($format)) $format = $format['id'];
		if(isset($this->debug)) {
			$debug = '';
			foreach($this->debug as $i=>$di);
				$debug.= "$i) ".print_r($di,true).chr(10);
			switch($format) {
			case 'txt':
				return "$content\n\n#-- DEBUG\n$debug\n";
			case 'json':
				return json_encode(array($content,$this->debug));
			default:
				$s = "<!DOCTYPE html>\n<html>\n<body>\n$content\n</html>\n<!-- DEBUG\n$debug-->\n";
				return $s;
			}
		} else {
			switch($format) {
			case 'txt':
				return $content;
			case 'json':
				return json_encode(array($content));
			default:
				return "<!DOCTYPE html>\n<html>\n<body>\n$content\n</html>\n";
			}
		}
	}
};

class engine extends area {
	static $first;
	public $line;
	public $req=array();
	private $heads=array();
	private $status=array();
	
	function __construct() {
		if(!isset(engine::$first)) engine::$first = $this;
		ob_start();
		$this->site_config();
		session_start();
		$this->open_db();
		$this->request_params();
	}
	
	function print_r($x) {
		$this->write(print_r($x,true),false);
	}
	
	function headers() {
		if(isset($this->heads[0])) {
			header($u=sprintf('%s %03d %s',$this->server['protocol'],$this->heads[0],$this->status_get((int)$this->heads[0])));
		}
		foreach($this->heads as $head=>$value) {
			if($head===0) { echo "cero"; continue; }
			if(is_array($value)) {
				foreach($value as $val) {
					header($u="$head: $val");
				}
			} else {
				header($u="$head: $value");
			}
		}
	}
	function header_set($header,$value,$how=SET_REPLACE) {
		switch($how) {
		case SET_ADD:
			if(!isset($this->heads[$header])) $this->heads[$header] = array();
			array_push($this->heads[$header],$value);
			break;
		case SET_EMPTY:
			if(isset($this->heads[$header])) return;
		default:
			$this->heads[$header] = $value;
		}
	}
	function status_set($n,$alt='Undefined') { $this->head[0] = $n; if(!isset($this->status[(int)$n])) $this->status[(int)$n] = $alt; }
	function status_get($n,$alt='Undefined') { return isset($this->status[(int)$n])? $this->status[(int)$n]: $alt; }
	
	function finish() {
		$this->close_db();
		$this->set('debug',ob_get_clean(),SET_ADD);
		$this->set('debug',$this,SET_ADD);
		return area::finish();
	}
	/*
	function output() {
		$this->set('debug',ob_get_clean());
	}
	
	function close() {
		$this->close_db();
		$debug = $this->get('debug','').ob_get_clean();
		if(!empty($debug)) {
			switch($this->get('req/format')) {
			case 'txt':
				echo "\n# -- DEBUG\n$debug\n";
				break;
			case 'json':
				echo chr(10).json_encode(array("debug"=>$debug));
				break;
			default:
				echo "\n<!-- DEBUG\n$debug\n-->\n";
			}
		}
	}
	*/
	function open_db() {
		$server = $this->get('db/server','localhost');
		$database = $this->get('db/database','orugadata');
		$user = $this->get('db/user','root');
		$password = $this->get('db/key','');
		$this->dbo = new mysqli($server,$user,$password,$database);
		if($this->dbo->connect_errno) die('No se pudo conectar: '.$this->dbo->connect_error);
	}
	function close_db() {
		$this->dbo->close();
	}
	
	function site_config() {
		if(file_exists('config.php'))
		require_once 'config.php';
	}
	
	function request_params() {
		$this->set('header/Content-type','text/plain');
		$this->content = '';
		foreach($_SERVER as $key=>$val) {
			if(substr($key,0,12)=='HTTP_ACCEPT_')
				$this->set(abconcat('accept',substr($key,12)),$val);
			elseif(substr($key,0,5)=='HTTP_')
				$this->set(abconcat('http',substr($key,5)),$val);
			elseif(substr($key,0,7)=='SERVER_')
				$this->set(abconcat('server',substr($key,7)),$val);
			elseif(substr($key,0,8)=='REQUEST_')
				$this->set(abconcat('request',substr($key,8)),$val);
			elseif(substr($key,0,9)=='REDIRECT_')
				$this->set(abconcat('redirect',substr($key,9)),$val);
			else
				$this->set(abconcat('settings',$key),$val);
		}
		foreach($_REQUEST as $key=>$val) {
			$this->set(abconcat('input',$key),$val);
		}
		foreach($_SESSION as $key=>$val) {
			$this->set(abconcat('session',$key),$val);
		}
		//== REQUESTED ELEMENTS
		$rq_site = $this->get('input/site');
		$rq_lang = $this->get('input/lang');
		$rq_sec  = $this->get('input/sec');
		$rq_doc  = $this->get('input/doc');
		$rq_page = $this->get('input/page');
		$rq_form = $this->get('input/format');
		$rq_style= $this->get('input/style');
		$rq_act  = $this->get('input/action');
		//== REQUEST LINE
		$rq_line = $this->get('redirect/url',$this->get('request/uri'));
		$rq_line = substr($rq_line,IL_LINEOFFSET);
		$this->line = $rq_line;
		//== SITE
		if(!isset($rq_site))
			$rq_site = $this->get('site/value');
		if(!isset($rq_site)) {
			$server_name = explode('.',$this->get('server/name','www'));
			$rq_site = $server_name[0];
		}
		$this->set('req/site',$rq_site);
		//== LANGUAGE
		#check if there is a language parameter in the request line
		if(!isset($rq_lang)) {
			if(preg_match('{^([a-z]{2})/(.*)}',$rq_line,$m)) {
				$rq_lang = $m[1];
				$rq_line = $m[2];
			}
			elseif(preg_match('{^(.*)\.([a-z]{2})\.(\w+)$}',$rq_line,$m)) {
				$rq_line = $m[1];
				$rq_lang = $m[2];
				if(!isset($rq_form)) $rq_form = $m[3];
			}
			elseif(preg_match('{^(.*)\.([a-z]{2})$}',$rq_line,$m)) {
				$rq_line = $m[1];
				$rq_lang = $m[2];
			}
		}
		#get available language and check against user accepted preferences
		if(!isset($rq_lang)) {
			$langs = $this->db_select('language','id');
			e_univalue($langs);
			$accept_lang = explode(',',$this->get('accept/language','en'));
			foreach($accept_lang as $l)
				if(in_array(substr($l,0,2),$langs)) {
					$plang = substr($l,0,2);
					break;
				}
			#if no match, use 'english' if defined, else first site configured languages.
			$rq_lang = isset($plang)? $plang: (in_array('en',$langs)? 'en': $langs[0]);
		}
		$this->set('req/lang',$rq_lang);
		//== FORMAT
		if(!isset($rq_form)) {
			if(preg_match('{^(.*)\.(\w+!?)$}',$rq_line,$m)) {
				$rq_line = $m[1];
				$rq_form = $m[2];
			}
		}
		if(isset($rq_form)) {
			$r = $this->db_select('res_extension','*',array('ext'=>"=$rq_form"));
			if($r) {
				$rq_form = $r[0]['format'];
				$rq_pact = $r[0]['action_type'];
				$rf = $this->db_select('res_format','*',array('id'=>"=$rq_form"));
				if($rf)
					$this->set('format',$rf[0]);
			}
		}
		if(!isset($rq_form)) $rq_form = 'html';
		$this->set('req/format',$rq_form);
		$this->set('format',$rq_form,SET_EMPTY);
		//== SECTION
		if(!isset($rq_sec)) {
			$secs = $this->db_select('res_section');
			$prio = -1;
			if($secs)
			foreach($secs as $row) {
				$this->write($row['case']);
				if(preg_match("#{$row['case']}#",$rq_line,$m)) {
					if($row['priority'] > $prio) {
						$prio = $row['priority'];
						$rq_sec = $row['id'];
						$psec  = $m[1];
						$pline = $m[2];
					}
				}
			}
			if($prio>=0) {
				$rq_line = $pline;
				if(!empty($psec)) $this->set('req/sid',$psec);
			}
		}
		$this->set('req/section',$rq_sec);
		//== STYLE
		if(!isset($rq_style)) $rq_style = 'default';
		$this->set('req/style',$rq_style);
		//== ACTION
		if(!isset($rq_act)) {
			$rq_method = strtoupper($this->get('request/method','GET'));
			if(isset($rq_pact) and $rq_pact=='active')
				$rq_act = $rq_method=='POST'? 'submit': 'edit';
			else
				$rq_act = $rq_method=='POST'? 'refresh': 'show';
		}
		$this->set('req/action',$rq_act);
		//== PAGE
		if(!isset($rq_page)) {
			if(preg_match('{^(.*)/(\d+|index|all|edit)$}',$rq_line,$m)) {
				$rq_line = $m[1];
				$rq_page = $m[2]=='edit'? 'index': $m[2];
			} else $rq_page = 'index';
		}
		$this->set('req/page',$rq_page);
		//== DOCUMENT
		$this->set('req/document',isset($rq_doc)? $rq_doc: (isset($rq_line)?$rq_line:''));
		$this->set('formatpage',"style/$rq_style/index.$rq_form");
	}
	
	function db_esc($s) { return $this->dbo->real_escape_string($s); }
	function db_query($q) { $this->set('queries',trim($q),SET_ADD); return $this->dbo->query($q); }
	function db_select($table,$cols='*',$where=null,$orderby=null,$desc=false,$limit=100,$limitfrom=0) {
		if(is_array($cols) and count($cols)) $cd='`'.implode('`,`',$columns).'`';
		elseif($cols=='*') $cd = $cols;
		elseif(is_string($cols)) {
			if(preg_match('{[\w_]+}',$cols)) $cd = "`$cols`";
			else $cd = "'".$this->db_esc($cols)."'";
		} else $cd = '*';
		$query = "SELECT $cd";
		$cltab = $this->db_esc($table);
		$query.= " FROM `$cltab`";
		if(is_array($where) && count($where)) {
			$w = array();
			foreach($where as $key=>$val) {
				$eval = $this->db_esc(substr($val,1));
				switch($c = substr($val,0,1)) {
				case '=': case '<': case '>':
					$w[] = "`$key` $c '$eval'";
					break;
				case '(':
					$x = explode(',',trim($val,'()'));
					$w[] = "`$key` IN ('".implode("','",$x)."')";
					break;
				default:
					$eval = $this->db_esc($val);
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
		if(($r = $this->db_query($query))!==false) {
			$a = $r->fetch_all(MYSQLI_ASSOC);
			$r->free();
			return $a;
		}
		return false;
	}
	
	function area_set($area) {
		$this->set("areas/$area",new area());
	}
};

function e_set($param,$value,$how=SET_REPLACE) { engine::$first->set($param,$value,$how); }
function e_get($param,$default=null,$how=GET_UNSET) { return engine::$first->get($param,$default,$how); }
function e_write($x,$n=true) { engine::$first->write($x,$nl); }
function e_print($x) { engine::$first->write(print_r($x,true),false); }
function e_reindex(&$ar,$key) {
	$n = count($ar);
	for($i=0;$i<$n;$i++) {
		$ar[$ar[$i][$key]] = $ar[$i];
		unset($ar[$i]);
	}
	return $ar;
}
function e_univalue(&$ar) {
	$r = array();
	foreach($ar as $row) $r=array_merge($r,array_values($row));
	return $ar = $r;
}
function e_query($q) { return engine::$first->db_query($q); }
function e_select($t,$cc='*',$wh=null,$ob=null,$d=false,$l=100,$lf=0) { return engine::$first->db_select($t,$cc,$wh,$ob,$d,$l,$lf); }
function e_db_esc($s) { return engine::$first->db_esc($s); }

function set_area($area) { return engine::$first->area_set($area); }

?>