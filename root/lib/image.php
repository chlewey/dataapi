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

class image extends rest_api {
	function __construct($imagetype) {
		rest_api::__construct($imagetype,'0.2');
		$this->db = require_once 'config/db.php';
		$this->makepic = false;
		$this->line = isset($_SERVER['REDIRECT_URL'])? $_SERVER['REDIRECT_URL']: (
			isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI']: '');
	}

	function setbases($basedir,$baseurl) {
		$this->basedir = $basedir;
		$this->baseurl = $baseurl;
		$line = $this->line;
		if(preg_match('{^/?(\w+)/(\d+)(?:/(\w+)(?:\.(\w+))?\b)?()}',$line,$m))
			return $this->get_by_num($m);
		if(preg_match('{^/?(\w+)/G/(\w+)(?:/(\w+)(?:\.(\w+))?\b)?()}',$line,$m))
			return $this->get_by_group($m);
		if(preg_match('{^/?(\w+)/([\w/]+)(?:\.(\w+))?\b()}',$line,$m))
			return $this->get_by_resource($m);
		return $this->status(404,"Misunderstood requested '$line'.");
	}

	function ispic() {
		return $this->makepic;
	}

	function get_image($type,$index,$size='',$fmt='') {
		$this->response('query',array($type=>$index));
		$data = $this->db->select_first($type,'*',array('idx'=>"=$index"));
		if(empty($data))
			return $this->status(404,"Image not found.  There is no $type with index $index");
		if(empty($size)) {
			$r = array();
			foreach($data as $s=>$fn) {
				if($s=='idx') continue;
				if($s=='basedir') continue;
				if(empty($fn)) continue;
				$r[$s] = $this->baseurl.$data['basedir'].$fn;
			}
			$this->response($type,$r);
			return;
		}
		if(empty($data[$size]))
			return $this->status(404,"Size '$size' not found.");
		return $this->fiximage($data['basedir'],$data[$size],$fmt);
	}

	function get_by_num($m) {
		$api = $this->R['api'];
		$rq = $m[1];
		$idx = (int)$m[2];
		$size= $m[3];
		$fmt = $m[4];

		if($api!=$rq)
			return $this->status(403,"API mismatch '$rq' != '$api'.");
		
		return $this->get_image($rq,$idx,$size,$fmt);
	}

	function get_by_group($m) {
		$api = $this->R['api'];
		$rq = $m[1];
		$group = $m[2];
		$size = $m[3];
		$ext = $m[4];

		if($api!=$rq)
			return $this->status(403,"API mismatch '$rq' != '$api'.");
		
		$msg = 'Group ['.implode('],[',$m).']';
		return $this->status(200,$msg);
	}

	function get_by_resource($m) {
		$api = $this->R['api'];
		$rq = $m[1];
		$resource = explode('/',$m[2]);
		$ext = $m[3];

		if($api!=$rq)
			return $this->status(403,"API mismatch '$rq' != '$api'.");
		
		$r = array();
		$size = array_pop($resource);
		if(!in_array($size,array('tiny','small','medium','big','full')) && !empty($size)) {
			array_push($resource,$size);
			$size = '';
		}
		if(count($resource)>0) {
			$base = $resource[0];
			$q = $this->db->select_first('user','*',array('id'=>"=$base"));
			if($q===false) return $this->status(404,"Station or user '$base' not found.");
			$img = $q[$rq];
			$r['base'] = $base;
		}
		if(count($resource)>1) {
			$item = (int)$resource[1];
			$q = $this->db->select_first('dl_instrument','*',array('station'=>"=$base",'address'=>"=$item"));
			if($q===false) return $this->status(404,sprintf("Instrument '%s/%03d' not found.",$base,$item));
			if(!empty($q[$rq])) $img = $q[$rq];
			$r['item'] = $item;
		}
		if(count($resource)>2) {
			$meter = $resource[2];
			$q = $this->db->select_first('dl_meter','*',array('station'=>"=$base",'address'=>"=$item",'keyword'=>"=$meter"));
			if($q===false) return $this->status(404,sprintf("Meter '%s' not found at instrument '%s/%03d'.",$meter,$base,$item));
			if(!empty($q[$rq])) $img = $q[$rq];
			$r['meter'] = $meter;
		}
		
		if(empty($img))
			return $this->status(404,'Requested resource has no $rq.');
		$this->response('resource',$r);
		return $this->get_image($rq,$img,$size,$fmt);
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
