<?php

require "config.php";
defined('INDEX_PAGE') OR define('INDEX_PAGE', "");
$url = INDEX_PAGE ==  "" ? BASE_URL : BASE_URL.INDEX_PAGE."/";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "schedules/schedule_run"); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
echo curl_exec($ch);
curl_close($ch);

?>