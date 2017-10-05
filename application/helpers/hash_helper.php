<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('salt') )
{
	function salt($length = 32){
		$intermediateSalt = md5(uniqid(rand(), true));
		return substr($intermediateSalt, 0, $length);
	}
}

