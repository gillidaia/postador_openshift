<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('flash_bag') )
{
  function flash_bag($message, $type = "info", $icon = false, $close = false, $html = false,$notifId = false)
  {
	if($icon == true){
		switch($type){
			case "success":
			$icon = "check-circle"; break;
			case "info":
			$icon = "info-circle"; break;
			case "warning":
			$icon = "exclamation-circle"; break;
			case "danger":
			$icon = "exclamation-triangle"; break;
			case "primary":
			$icon = "info-circle"; break;
		}
	}
     return array(
     	'message' => $message,
     	'type' =>  $type,
     	'icon' => $icon,
     	'close' => $close,
     	'html' => $html,
     	'notif_Id' => $notifId,
     );
  }
}

