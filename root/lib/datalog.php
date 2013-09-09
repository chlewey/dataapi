<?php
if(!empty($debuging)) {
	ini_set('display_errors', true);
	error_reporting(E_ALL);
}
date_default_timezone_set('America/Bogota');

require_once "lib/api.php";
require_once "lib/db.php";

class datalog extends api {
	function __construct() {
		api::__construct('datalog','0.41');
		$this->db = new db('localhost','lusedo','lu53d0','orugadata');
		$l = isset($_SERVER['REDIRECT_URL'])? $_SERVER['REDIRECT_URL']: (
			isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI']: '');
		if(preg_match('{/?datalog(?:/(\w+)(?:/(\d+))?(?:/(\w+))?)?\b(.*)}',$l,$m)) {
			$this->base = $m[1];
			$this->item = (int)$m[2];
			$this->action = $m[3];
			$this->ext = $m[4];
		}
		$this->set_user();
		$this->set_base();
	}

	function set_user() {
		$this->user = isset($_SESSION['user'])? $_SESSION['user']: array();
		if(time()-$this->since() > 3600)
			$this->user = array('name'=>null,'former'=>$this->user['name'],'authenticated'=>false);
		else
			$this->user['since'] = date('Y-m-d H:i');
		$this->auth_user_php();
		$this->auth_user_request();
		$this->user_params();
		$_SESSION['user'] = $this->user;
	}
	function auth() { return !empty($this->user['authenticated']); }
	function since() { return empty($this->user['since'])? 0: strtotime($this->user['since']); }
	function is_base() { return isset($this->user['station']); }
	function auth_user($user,$passwd) {
		$hash = md5("[$user:$passwd]");
		return $this->db->select_first('user','*',array('id'=>"=$user",'hatch'=>"=$hash"));
	}
	function user_params() {
		if(empty($this->user['authenticated'])) return;
		$un = $this->user['name'];
		$wh = array('user'=>"=$un");
		$pars = db_select('user_data','*',$wh);
		if($pars) foreach($pars as $par) {
			$pn = $par['param'];
			if($par['param_idx']!=1) $pn.= '-'.$par['param_idx'];
			$this->user[$pn].= $par['value'];
		}
		$gr = db_select('user_group','*',$wh);
		if($gr) {
			$this->user['groups'] = array();
			foreach($gr as $group) 
				$this->user['groups'][$group['group']] = $group['role'];
		}
		$bs = db_select_first('dl_station',array('name','ip','group'),array('station'=>"=$un"));
		if($bs) $this->user['station'] = $bs;
	}
	function user_role($group='this_site') {
		return isset($this->user['groups'][$group])? $this->user['groups'][$group]: 0;
	}
	function auth_user_php() {
		if(!isset($_SERVER['PHP_AUTH_USER'])) return;
		$u = $_SERVER['PHP_AUTH_USER'];
		$pw = $_SERVER['PHP_AUTH_PW'];
		if(($q = $this->auth_user($u,$pw))==false) {
			$this->user = array(
				'name'=>null,
				'reported'=>$u,
				'authenticated'=>false
			);
		} else {
			$this->user = array(
				'name'=>$u,
				'fullname'=>$q['name'],
				'authenticated'=>true,
				'since'=>date('Y-m-d H:i')
			);
		}
	}
	function auth_user_request() {
		if(!isset($_REQUEST['user'])) return;
		$u = $_REQUEST['user'];
		$pw = $_REQUEST['passwd'];
		if(($q = $this->auth_user($u,$pw))==false) {
			$this->user = array(
				'name'=>null,
				'reported'=>$u,
				'authenticated'=>false
			);
		} else {
			$this->user = array(
				'name'=>$u,
				'fullname'=>$q['name'],
				'authenticated'=>true,
				'since'=>date('Y-m-d H:i')
			);
		}
	}

	function stations(&$base) {
		$this->bases = array();
		$un = $this->user['name'];
//**/		$this->response('JJJJJ',array($base,$un));
		$this->basedata = $bb = db_select_key('dl_station','station');
		if(!$this->auth()) {
			foreach($bb as $b=>$d)
				if($d['public']) $this->bases[] = $b;
		} elseif($this->user_role()>=7) {
			$this->bases = array_keys($bb);
		} else {
			$wh = array('user'=>"=$un");
			$ub = db_select_one('dl_userstation','station',$wh,true);
			if($ub) $this->bases = $ub;
			foreach($bb as $b=>$d) {
				$gr = $d['group'];
				if($this->user_role($gr)>=7) $this->bases[] = $b;
			}
		}
		if($base==$un) return true;
		if(empty($base)) {
			if(isset($bb[$un])) {
				$base = $un;
				return true;
			}
			return !empty($bases);
		}
		if(isset($bb[$base]) && $bb[$base]['public']) return true;
		return in_array($base,$this->bases);
	}
	function base_info($base) {
/*		if(!isset($this->basedata[$base])) $this->resp_add('bases',null,$base);
		else
			$this->resp_add('bases',array(
				'ip'=>$this->basedata[$base]['ip'],
				'name'=>$this->basedata[$base]['name'],
				'group'=>$this->basedata[$base]['group'],
				),$base);
*///		$this->resp_add('bases',$base);
	}
	function set_base() {
		$base = !empty($this->base)? $this->base: (
			!empty($_REQUEST['base'])? $_REQUEST['base']: '');
		$item = !empty($this->item)? $this->item: (
			!empty($_REQUEST['item'])? (int)$_REQUEST['item']: 0);
		$this->can = $can = $this->stations($base);
		if($this->debug()) $this->response('base',array($can,$base,$this->bases));
		if($can && $base) {
			$this->base_info($base);
			$this->base = $base;
		} else
			foreach($this->bases as $b)
				$this->base_info($b);
	}

	function go() {
		$action = !empty($this->action)? $this->action: (
			empty($_REQUEST['action'])? false: $_REQUEST['action']);
		if(method_exists($this,$act="go_$action")) return $this->$act();
		if($action) $this->resp_add('warning',"Unspecified action '$action'.");
		elseif($this->user['name']==$this->base) { $this->go_keepalive(); }
	}

	function go_keepalive() {
		$b = $this->base;
		$o_ip = $this->basedata[$b]['ip'];
		$n_ip = $_SERVER['REMOTE_ADDR'];
		if($o_ip != $n_ip) {
			$this->db->update('dl_station',array('ip'=>$n_ip),array('station'=>"=$b"));
			$this->status(201,"Keepalive for '$b' from $n_ip!",false);
		} else {
			$this->status(200,"Keepalive for '$b' from $n_ip.",false);
		}
	}

	function lunits($lu,$n=1) {
		switch($lu) {
		case 'sec': return $n;
		case 'min': return $n*60;
		case 'hour': return $n*3600;
		case 'day': return $n*86400;
		case 'week': return $n*86400*7;
		case 'month': return $n*86400*30;
		case 'year': return $n*86400*365;
		default: return $n*(int)$lu;
		}
        }
	function checkdates() {
		if(isset($_REQUEST['date'])) {
			$dt0 = strtotime($_REQUEST['date'].' '.(isset($_REQUEST['time'])?$_REQUEST['time']:'00:00'));
			$count = (int)$_REQUEST['count'];
			if(isset($_REQUEST['enddate'])) {
				$dt1 = strtotime($_REQUEST['enddate'].' '.(isset($_REQUEST['endtime'])?$_REQUEST['endtime']:'24:00'));
				$delta = $dt1-$dt0;
			} else {
				$delta = $this->lunits($_REQUEST['lunits'],$_REQUEST['lapse']);
				$dt1 = $dt0 + $delta;
			}
			return array($dt0,$dt1,$delta,$count);
		}
		$d = time();
		$d = $d-($d%3600);
		return array($d-86400,$d,86400,288);
	}
	function checkitems() {
		$on = array();
		$off = array();
		$bases = empty($this->base)? $this->bases: array($this->base);
		foreach($_REQUEST as $k=>$v) {
			if(!$v) continue;
			$kr = explode('_',$k);
			if(count($kr)!=4) continue;
			if(array_shift($ke)!='update') continue;
			if(!in_array($kr[0],$bases)) continue;
			$on[] = sprintf('%s/%03d/%s',$kr[0],(int)$kr[1],$kr[2]);
		}
		foreach($bases as $bb) {
			$where = array('station'=>"=$bb",'type'=>'=common');
			if(!empty($this->item)) $where['address'] = "=".$this->item;
			$q = $this->db->select('dl_meter',array('address','keyword'),$where);
			foreach($q as $r) {
				$k = sprintf('%s/%03d/%s',$bb,(int)$r['address'],$r['keyword']);
				if(!in_array($k,$on)) $off[] = $k;
			}
		}
		sort($on);
		sort($off);
		return empty($on)? $off: $on;
	}
	function go_show() {
		$tp = $this->checkdates();
		$ip = $this->checkitems();
		$params = date("Y-m-d H:i:s\n",$tp[0]);
		$params.= date("Y-m-d H:i:s\n",$tp[1]);
		$params.= implode(chr(10),$ip);
		$phash = str_replace(array('+','/','=='),array('_','-','!'),base64_encode(md5($params,true)));
		if(file_exists($vfn = DATALOG_WWW.$phash.'.csv')) {
			$csv = file_get_contents($dfn);
		} else {
		}
	}

	function close() {
		$this->db->close();
		if($this->debug()) {
			$this->response('user',$this->user);
			$this->response('queries',db::$log);
			$this->response('request',$_REQUEST);
		}
		api::close();
	}
};

?>
