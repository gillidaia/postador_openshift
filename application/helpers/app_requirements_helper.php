<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('check_requirements') )
{
  	function check_requirements()
  	{
  		
  		$requirements = array(
  			"ok" => TRUE,
  			"php_v" => array("title"=>"PHP Verion >= 5.4.0","status"=>'ok'),
  			"pdo" => array("title"=>"PDO extension","status"=>'ok'),
  			"mysql" => array("title"=>"MYSQL extension","status"=>'ok'),
  			"mysqli" => array("title"=>"Mysqli extension","status"=>'ok'),
  			"curl" => array("title"=>"Curl Library","status"=>'ok'),
  			"curl_init" => array("title"=>"Curl_init Function","status"=>'ok'),
  			"curl_exec" => array("title"=>"Curl_exec Function","status"=>'ok'),
  			"outbound" => array("title"=>"Outbound connection - Connection to Facebook API failed","status"=>'ok'),
  		);

  		/*
  		|--------------------------------------------------------------------------
  		| Check php version
  		|--------------------------------------------------------------------------
		|
		*/
  		if (version_compare(PHP_VERSION, '5.4.0', '<')) {
  			$requirements["php_v"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
  		|--------------------------------------------------------------------------
  		| Check PDO
  		|--------------------------------------------------------------------------
		|
		*/
		if (!class_exists('PDO')){
			$requirements["pdo"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
  		|--------------------------------------------------------------------------
  		| Check MYSQL
  		|--------------------------------------------------------------------------
		|
		*/
		if (!in_array("mysql",PDO::getAvailableDrivers(),TRUE)){
	        $requirements["mysql"]["status"] = "fail";
			$requirements["ok"] = FALSE;
	    }
		/*
  		|--------------------------------------------------------------------------
  		| Check MYSQL
  		|--------------------------------------------------------------------------
		|
		*/
	    if(!function_exists('mysqli_connect')){
			$requirements["mysqli"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
		|--------------------------------------------------------------------------
		| Check cURL library
		|--------------------------------------------------------------------------
		|
		*/
		if (!extension_loaded('curl')) {
			$requirements["curl"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
		|--------------------------------------------------------------------------
		| Check curl_init
		|--------------------------------------------------------------------------
		|
		*/
		if(!function_exists('curl_init')){
			$requirements["curl_init"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
		|--------------------------------------------------------------------------
		| Check curl_exec
		|--------------------------------------------------------------------------
		|
		*/
		if(!function_exists('curl_exec')){
			$requirements["curl_exec"]["status"] = "fail";
			$requirements["ok"] = FALSE;
		}
		/*
		|--------------------------------------------------------------------------
		| Check connection to Facebook API
		|--------------------------------------------------------------------------
		|
		*/
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, "http://graph.facebook.com/"); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$res = curl_exec($ch);
		if($res === false){
		   $requirements["outbound"]["status"] = "fail";
		   $requirements["ok"] = FALSE;
		}
		curl_close($ch);

		return $requirements;
  	}

}
?>