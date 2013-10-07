<?php
if(!empty($debuging)) {
	ini_set('display_errors', true);
	error_reporting(E_ALL);
}
require_once "lib/db.php";
require_once "lib/api.php";

$GLOBALS['formats'] = array(
	'png' => 'png',
	'gif' => 'gif',
	'jpeg' => 'jpeg',
	'jpg' => 'jpeg',
);

class image extends api {
	function __construct($imagetype) {
		api::__construct($imagetype,'0.1');
		$this->db = require_once 'config/db.php';
		$this->makepic = false;
		$this->line = isset($_SERVER['REDIRECT_URL'])? $_SERVER['REDIRECT_URL']: (
			isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI']: '');
	}

	function setbases($basedir,$baseurl) {
		$this->basedir = $basedir;
		$this->baseurl = $baseurl;
		if(preg_match('{/?(\w+)/(\d+)(?:/(\w+)(?:\.(\w+))?\b)?()}',$this->line,$m))
			return $this->get_by_num($m);
		if(preg_match('{/?(\w+)/(\d+)(?:/(\w+)(?:\.(\w+))\b)()}',$this->line,$m))
			return $this->get_by_res($m);
	}

	function ispic() {
		return $this->makepic;
	}

	function get_by_num($m) {
		$api = $this->R['api'];
		$rq = $m[1];
		$idx = (int)$m[2];
		$size= $m[3];
		$fmt = $m[4];

		if($api!=$rq)
			return $this->status(403,"API mismatch '$rq' != '$api'.");

		$data = $this->db->select_first($m[1],'*',array(idx=>"=$idx"));
		if(empty($data))
			return $this->status(404,'Image index not found.');
		if(empty($size)) {
			$r = array();
			foreach($data as $s=>$fn) {
				if($s=='idx') continue;
				if($s=='basedir') continue;
				if(empty($fn)) continue;
				$r[$s] = $this->baseurl.$data['basedir'].$fn;
			}
			$this->response($api,$r);
			return;
		}
		if(empty($data[$size]))
			return $this->status(404,"Size '$size' not found.");
		return $this->fiximage($data['basedir'],$data[$size],$fmt);
	}

	function fiximage($bd,$fn,$fmt) {
		global $formats;
		$fbd = $this->basedir.$bd;
		$ffn = $fbd.$fn;
		if(!file_exists($ffn))
			return $this->status(404,"Image not found $ffn.");
		if(($n = strrpos($fn,'.'))==false)
			return $this->status(404,"Malformated image (empty extenssion).");
		$bfmt = strtolower(substr($fn,$n+1));
		if(!isset($formats[$bfmt]))
			return $this->status(404,"Malformated image (unknown extenssion).");
		$afmt = $formats[$bfmt];

		if(!empty($fmt)) {
			$lfmt = strtolower($fmt);
			if(!isset($formats[$lfmt]))
				return $this->status(404,"Unrecognized extenssion");
			$rfmt = $formats[$lfmt];
			if($rfmt != $afmt) {
				$nfn = $fbd.substr($fn,0,$n+1).$rfmt;
				if(!file_exists($nfn)) {
					$createimage = "imagecreatefrom$afmt";
					$writeimage = "image$rfmt";
					$image = $createimage($ffn);
					$writeimage($image,$nfn);
					imagedestroy($image);
				}
				$ffn = $nfn;
				$afmt = $rfmt;
			}
		}

		$this->makepic = true;
		$this->mimetype = 'image/'.$afmt;
		$this->file = $ffn;
	}

	function get_by_res($m) {
		if($m[1] != $this->R['api']) {
			$this->status(403,"API mismatch '{$m[1]}' != '{$this->R['api']}'.");
			return;
		}
	}

	function returnpic() {
		$this->db->close();
		header('Content-type: '.$this->mimetype);
		readfile($this->file);
	}

	function close() {
		$this->db->close();
		api::close();
	}
}
?>
