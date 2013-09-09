<?php

function set_area($area,$what='default',$data=null) {
	global $il;
	$il->areas[$area] = (empty($data) && strlen($what)>24)? array('print',$what): array($what,$data);
}

function unset_area($area) {
	global $il;
	unset($il->areas[$area]);
}

function area_exists($area) {
	global $il;
	return isset($il->areas[$area]);
}

function make_area($area,$default=null) {
	global $il;
	if($r=il_cache_area_get($area)) return $r;
	$areas = $il->areas;
	if(isset($areas[$area])) {
		$data = $areas[$area][1];
		switch($what=$areas[$area][0]) {
		case 'function':
			return $data();
		case 'print':
			return $data;
		case 'php':
			if (!empty($data)) {
				if(substr($data,0,1)=='/') $fn=substr($data,1);
				if(	file_exists($fn) ||
					file_exists($fn=$data) ||
					file_exists($fn="template/$temp/$data") ||
					file_exists($fn="data/areas/$data"))
					return include $fn;
				return "{Area '$area' without file '$data'}";
			}
		case 'html':
			if (!empty($data)) {
				if(substr($data,0,1)=='/') $fn=substr($data,1);
				if(	file_exists($fn) ||
					file_exists($fn=$data) ||
					file_exists($fn="template/$temp/$data") ||
					file_exists($fn="data/areas/$data"))
					return file_unbrace($fn);
				return "{Area '$area' without file '$data'}";
			}
		default:
			if($data) return $data;
			$temp = il_get('template','default');
			if(	file_exists($fn="template/$temp/$area.php") ||
				file_exists($fn="data/areas/$area.php") )
				return include $fn;
			if(	file_exists($fn="template/$temp/$area.html") ||
				file_exists($fn="data/areas/$area.html") ) {
				$html = file_unbrace($fn);
				if(il_get2('area',$area)=='cachable')
					il_cache_area_set($area,$html);
				return $html;
			}
			return "{Area '$area' incompletely set}";
		}
	}
	return $default? $default: "{area:$area}";
}

function make_menu($menu,$closed=true) {
	$mfn = "$menu.menu";
	if($r=il_cache_area_get($mfn)) return $r;
	$temp = il_get('template','default');
	if ( file_exists( $fn = "data/menu/$mfn" ) )
		$data = file($fn);
	elseif ( file_exists( $fn = "template/$temp/$mfn" ) )
		$data = file($fn);
	else
		$data = array();

	$c = "";
	$ll = -1;
	$i=0;
	$prep=$menu;

	foreach($data as $item) {
		preg_match("$(\t*)([\w\d-_/]*|(\w+):[^,]+),([^,]*),?([\w\d-]*)$",$item,$m);
		if($m) {
			$l = strlen($m[1]);
			if($ll<$l) {
				$c.="\n{$m[1]}<ul id=list-$prep class=menu-$l>";
			} elseif($ll>$l) {
				while($ll-->$l)
					$c.="</li>\n\t{$m[1]}</ul></li>";
			} else {
				$c.="</li>";
			}
			$href=$m[3]?$m[2]:($m[2]=='-'?null: ($m[2]? "/".$m[2].(substr($m[2],-1)=='/'?"":".html"): "/"));
			$prep=$m[5]?"{$m[5]}":"$menu-".++$i;
			$id="item-$prep";
			$cap = trim($m[4]);
			if($href)
				$c .= "\n{$m[1]}<li id=$id><a href=\"$href\">$cap</a>";
			else
				$c .= "\n{$m[1]}<li id=$id><span>$cap</span>";
			$ll = $l;
		}
	}
	while($ll>=0)
		$c.= "</li>\n".str_repeat("\t",$ll--).(($ll>=0||$closed)?"</ul>":"");
	$c.= "\n";
	return $c;
}

set_area('topnav','<span class=hidden>Skip to <a href=#search>search</a>, <a href=#topmenu>navigation</a>.</span>');
set_area('site','function','area_site');

function area_site() {
	return il_line_get('cannon')? '<h2><a href="/">'.il_get('sitename').'</a></h2>': '<h1>'.il_get('sitename').'</h1>';
}

?>