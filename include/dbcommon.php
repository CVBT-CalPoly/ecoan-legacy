<?php

$phpversion = explode('.', PHP_VERSION);
if($phpversion[0]*10+$phpversion[1]<53)
	set_magic_quotes_runtime(0);

if(@$_SERVER["REQUEST_URI"])
{
	$pinfo=pathinfo($_SERVER["REQUEST_URI"]);
	$dirname = @$pinfo["dirname"];
	$dir = explode("/",$dirname);
	$dirname="";
	foreach($dir as $subdir)
	{
		if($subdir!="")
			$dirname.="/".rawurlencode($subdir);
	}
	if($dirname!="")
	{
//		@session_set_cookie_params(0,$dirname."/");
	}
}
@session_cache_limiter("none");
@session_start();

error_reporting(E_ALL ^ E_NOTICE);

/// include php specific code
include("phpfunctions.php");

$useOldMysqlLib = (bool)0;



$host="localhost";
$user="dbo454375987";
$pwd="root";
$port="";
$sys_dbname="ecoan";





$cCharset = "utf-8";
$cCodepage = 65001;
$cMySQLNames = "utf8";
$gLoadSearchControls = 30;

$projectPath = '';

header("Content-Type: text/html; charset=".$cCharset);


include("locale.php");
include("events.php");
include("commonfunctions.php");
include("dbconnection.php");

include("dbfunctions.php");
include("dal.php");
include("appsettings.php");

set_error_handler("runner_error_handler");

// json support
$useUTF8 = true;
if(!function_exists('json_encode') || !$useUTF8 || $phpversion[0]*10+$phpversion[1]<54)
{
	include_once(getabspath("classes/json.php"));
	$GLOBALS['JSON_OBJECT'] = new Services_JSON(SERVICES_JSON_LOOSE_TYPE, $useUTF8);
               
    function my_json_encode($value){
    	return $GLOBALS['JSON_OBJECT']->encode($value);
    }
   
    function my_json_decode($value){
        $result = $GLOBALS['JSON_OBJECT']->decode($value);
        if(is_null($result) || empty($result))
    		return array();
    	else 
    		return $result;
	}
	function my_json_encode_unescaped_unicode($value)
	{
    	$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE, $useUTF8, false);
		return $json->encode($value);
	}
}
else
{
	function my_json_encode($value){
		return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
   
    function my_json_decode($value){
        $result = json_decode($value,true);
        if(is_null($result) || empty($result))
    		return array();
    	else 
    		return $result;
	}

	function my_json_encode_unescaped_unicode($value)
	{
		return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
	
}


?>
