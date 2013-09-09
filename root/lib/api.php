<?php

$status = array(
	200=>array('Ok','Ok'),
	201=>array('Updated','Ok'),
	401=>array('Unauthorized','Sorry, you\'re not authorized for accessing the requested resource'),
	403=>array('Forbiden','Sorry, the requested resource seems to be forbiden'),
	404=>array('Not found','The requested resource was not found in this server'),
	500=>array('Internal Server Error','Sorry, something went wrong'),
);

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
		if(isset($status[$s])) {
			header(sprintf("%s %03d %s",$po,$s,$status[$s][0]));
			$this->response('status',$s);
			if($title) $this->response('title',$status[$s][0]);
			$this->response('message',empty($msg)?$status[$s][1]:$msg);
		} else {
			header(sprintf("%s %03d %s",$po,404,$status[404][0]));
			$this->response('status',$s);
			if($title) $this->response('title','Unknown status');
			$this->response('message',empty($msg)?'Unknown status':$msg);
		}
	}
};
?>
