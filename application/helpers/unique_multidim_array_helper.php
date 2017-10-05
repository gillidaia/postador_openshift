<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('unique_multidim_array'))
{
	function unique_multidim_array($array, $key) { 
	    $temp_array = array(); 
	    $key_array = array(); 
	    
	    foreach($array as $val) {
	    	$val = (array)$val;
	        if (!in_array($val[$key], $key_array)) { 
	            $key_array[] = $val[$key]; 
	            $temp_array[] = $val; 
	        } 
	    } 
	    return $temp_array; 
	} 
}
?>