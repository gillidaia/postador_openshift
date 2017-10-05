<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('display_json') )
{
  function display_json($data, $status_code = 200, $status_message = '')
  {
     header('Cache-Control: no-cache, must-revalidate');
     header('Content-type: application/json');
     set_status_header($status_code, $status_message);
     echo json_encode($data,JSON_PRETTY_PRINT);
     exit;
  }
}

