<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('elfinder_access') )
{
  function elfinder_access($attr, $path, $data, $volume)
  {	
  	return strpos(basename($path), '.') === 0       // if file/folder begins with '.' (dot)
		? !($attr == 'read' || $attr == 'write')    // set read+write to false, other (locked+hidden) set to true
		:  null;   
  }
}

