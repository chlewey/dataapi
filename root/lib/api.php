<?php

class status {
	static $status = array(
		100=>array('Continue',''),
		101=>array('Switching Protocols',''),
		200=>array('Ok','Ok'),
		201=>array('Created','Ok'),
		202=>array('Accepted','Ok'),
		203=>array('Non-Authoritative Information',''),
		204=>array('No Content',''),
		205=>array('Reset Content',''),
		206=>array('Partial Content',''),
		300=>array('Multiple Choices',''),
		301=>array('Moved Permanently',''),
		302=>array('Found',''),
		303=>array('See Other',''),
		304=>array('Not Modified',''),
		305=>array('Use Proxy',''),
		306=>array('(Unused)',''),
		307=>array('Temporary Redirect',''),
		400=>array('Bad Request',''),
		401=>array('Unauthorized','Sorry, you\'re not authorized for accessing the requested resource'),
		402=>array('Payment Required',''),
		403=>array('Forbidden','Sorry, the requested resource seems to be forbidden'),
		404=>array('Not found','The requested resource was not found in this server'),
		405=>array('Method Not Allowed',''),
		406=>array('Not Acceptable',''),
		407=>array('Proxy Authentication Required',''),
		408=>array('Request Timeout',''),
		409=>array('Conflict',''),
		410=>array('Gone',''),
		411=>array('Length Required',''),
		412=>array('Precondition Failed',''),
		413=>array('Request Entity Too Large',''),
		414=>array('Request-URI Too Long',''),
		415=>array('Unsupported Media Type',''),
		416=>array('Requested Range Not Satisfiable',''),
		417=>array('Expectation Failed',''),
		500=>array('Internal Server Error','Sorry, something went wrong'),
		501=>array('Not Implemented',''),
		502=>array('Bad Gateway',''),
		503=>array('Service Unavailable',''),
		504=>array('Gateway Timeout',''),
		505=>array('HTTP Version Not Supported',''),
	);

	static function exists($n) { return isset(status::$status[$n]); }
	static function title($n,$alt=null) { return isset(status::$status[$n])?status::$status[$n][0]:$alt; }
	static function message($n,$alt=null) { return isset(status::$status[$n])?status::$status[$n][1]:$alt; }
	static function pair($n) { return isset(status::$status[$n])?status::$status[$n]:null; }
};

class api {
	public $R = array();
	function __construct($apiname,$apiversion) {
		$this->R = array('api'=>$apiname,'version'=>$apiversion);
		if(isset($GLOBALS['debuging'])) {
			$this->debuging = true;
			ob_start();
		}
		$this->SID = $SID = session_id();
		if(empty($SID)) {
			session_start();
			$this->SID = session_id();
		}
	}

	function debug() { return !empty($this->debuging); }

	function response($key,$value) {
		$this->R[$key] = $value;
	}
	function resp_add($key,$value,$key2=null) {
		if(!isset($this->R[$key])) $this->R[$key] = array();
		if(empty($key2))
			$this->R[$key][] = $value;
		else
			$this->R[$key][$key2] = $value;
	}

	function close() {
		if($this->debug()) {
			$d = ob_get_clean();
			if(!empty($d)) $this->R['debug'] = $d;
		}
		header('Content-type: application/json;charset=utf8');
		echo json_encode($this->R);
	}

	function status($s,$msg=null,$title=true) {
		global $status;
		$p = isset($_SERVER['SERVER_PROTOCOL'])? $_SERVER['SERVER_PROTOCOL']: 'HTTP 1.1';
		$po = str_replace(' ','/',$p);
		if(status::exists($s)) {
			header(sprintf("%s %03d %s",$po,$s,status::title($s)));
			$this->response('status',$s);
			if($title) $this->response('title',status::title($s));
			$this->response('message',empty($msg)?status::message($s):$msg);
		} else {
			header(sprintf("%s %03d %s",$po,404,status::title(404)));
			$this->response('status',$s);
			if($title) $this->response('title','Unknown status');
			$this->response('message',empty($msg)?'Unknown status':$msg);
		}
	}
};

class rest_api extends api {
	private $request_vars;
	private $data;
	private $http_accept;
	private $method;
	private $line_path;
	public $line;

	function __construct($apiname,$apiversion) {
		api::__construct($apiname,$apiversion);

		$this->line = $l = isset($_SERVER['REDIRECT_URL'])? $_SERVER['REDIRECT_URL']: (
			isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI']: '');
		if(($n=strpos($l,'?'))!==false)
			$this->line_path = explode('/',ltrim(substr($l,$n),'/'));
		else
			$this->line_path = explode('/',ltrim($l,'/'));
		$this->http_accept	= (strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml';

		$action = strtolower($_SERVER['REQUEST_METHOD']);
		if($action=='put') {
			$data = file_get_contents('php://input');
		}
		$this->request_vars = array();
		foreach($_POST as $k=>$v) {
			if($k=='data') { if(empty($data)) $data = $v; }
			elseif($k=='action') $action = strtolower($v);
			else $this->request_vars[$k] = $v;
		}
		foreach($_GET as $k=>$v) {
			if($k=='data') { if(empty($data)) $data = $v; }
			else $this->request_vars[$k] = $v;
		}

		$this->method = in_array($action,array('post','put','delete'))? $action: 'get';

		$this->data = json_decode($data);
		if(json_last_error != JSON_ERROR_NONE)
			$this->data = $data;
	}
	public function get($var,$alt=null) { return isset($this->request_vars[$var])?$this->request_vars[$var]:$alt; }
	public function get_data($alt=null) { return empty($this->data)? (isset($alt)? $alt: array()): $this->data; }
	public function get_method() { return $this->method; }
	public function get_path($n=null) { return isset($n)? $this->line_path[$n]: $this->line_path; }
	public function get_path_len() { return count($this->line_path); }
	
	public function go($param=null) {
		if(method_exists($this,$M='go_'.$this->method))
			return $this->$M($param);
		$this->response('method',$this->method);
		if(isset($params))
			$this->response('params',$params);
		return false;
	}
};
?>
