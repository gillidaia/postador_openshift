<?php
defined('BASEPATH') OR exit('No direct script access allowed');
if ( ! function_exists('curl_helper'))
{
    function curl_helper($url) {

    	if(USE_GET_FILE_CONTENTS){
    		require_once "general_helper.php";
    		$content = file_get_contents(FCPATH.toABSPath($url,BASE_URL));
    		
    		if($content == "" || $content == FALSE){
    			return FALSE;
    		}

    		return $content;
    	}

    	$agent= 'Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36';
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL,$url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_VERBOSE, true);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	    $result=curl_exec($ch);
	    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close( $ch );
        return $httpcode == "200" ? $result : FALSE;
    }
}


	