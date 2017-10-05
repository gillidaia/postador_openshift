<?php

$content = "<?php
/*
|--------------------------------------------------------
| Turning ON/OFF debug mode (for maintenance mode)
|--------------------------------------------------------
|
*/
define('DEBUG_MODE', FALSE);
/*
|--------------------------------------------------------
| Default language
|--------------------------------------------------------
|
*/
define('DEFAULT_LANG','english');
/*
|--------------------------------------------------------
| Default language
|--------------------------------------------------------
|
*/
define('DEFAULT_TIMEZONE','UTC');
/*
|--------------------------------------------------------
| The uploads folder name
|--------------------------------------------------------
|
*/
define('UPLOADS_FOLDER','files');
/*
|--------------------------------------------------------
| Maximum upload file size. This size is per files in KB (Media library)
| 1000 = 1MB
|--------------------------------------------------------
|
*/
define('UPLOADS_MAX_SIZE_IMAGE','1000');
define('UPLOADS_MAX_SIZE_VIDEO','30000');
/*
|--------------------------------------------------------
| Cookie name
|--------------------------------------------------------
|
*/
define('COOKIE_NAME','".$cookie_name."');
/*
|---------------------------------------------------------
| App settings
|---------------------------------------------------------
*/
Define('DB_HOST',	'".$config_host."');
define('BD_DRIVER',	'".$config_dbDriver."');
Define('DB_NAME',	'".$config_dbName."');
Define('DB_USER',	'".$config_dbUser."');
Define('DB_PASS',	'".$config_dbPass."');
/*
|---------------------------------------------------------
| App URL must be difened
|---------------------------------------------------------
*/
Define('BASE_URL',	'".$siteURL."');
/*
|---------------------------------------------------------
| Index File
|---------------------------------------------------------
*/
Define('INDEX_PAGE', '".$index_page."');
/*
|---------------------------------------------------------
| App folder to store temporary files
|---------------------------------------------------------
*/
Define('TMP_PATH',	sys_get_temp_dir());
/*
|---------------------------------------------------------
| Mail settings
|---------------------------------------------------------
*/
Define('MAIL_PROTOCOL','mail');  // mail|smtp
Define('SMTP_HOST','');
Define('SMTP_USER','');
Define('SMTP_PASS','');
Define('SMTP_PORT', 465);
Define('SMTP_CRYPTO',''); // tls|ssl
/*
|---------------------------------------------------------
| App token used in some places for security
|---------------------------------------------------------
*/
Define('APP_TOKEN', '".md5(uniqid(rand(), true))."');
/*
|---------------------------------------------------------
| Sys installed
|---------------------------------------------------------
*/
Define('SYS_INSTALLED',	".$config_sys_installed.");
/*
|---------------------------------------------------------
| Date that will be injected on each post status position
|---------------------------------------------------------
*/
Define('FB_PDP_POSITTION', 'bottom');
/*
|---------------------------------------------------------
| Send images as data instead of sending image URL
|---------------------------------------------------------
*/
Define('FB_SEND_IMAGE_AS_MP', TRUE);
/*
|---------------------------------------------------------
| Using file_get_contens Instead of CURL
|---------------------------------------------------------
*/
Define('USE_GET_FILE_CONTENTS', TRUE);
/*
|---------------------------------------------------------
| Using multi Thread to send posts
|---------------------------------------------------------
*/
Define('USE_MULTI_THREAD', TRUE);
/*
|---------------------------------------------------------
| Unique post format (1 = Random string, 2 = Current Date time, 3 - Mixte of 1 - 2)
|---------------------------------------------------------
*/
Define('UNIQUE_POST_FORMAT', 1);
/*
|  Display less data
*/
define('LESS_DATA_ON_DATATABLE', FALSE);
/*
|  Wither to Use Datatable plugin or not, if not sample table filter will be used instead and no pagination
*/
define('USE_DT_PLUGIN', TRUE);
?>";

$fp = fopen($configFile, 'w');
if($fp){
	flock($fp, LOCK_EX);
	ftruncate($fp, 0);
	fseek($fp, 0);
	fwrite($fp, $content);
	flock($fp, LOCK_UN);
	fclose($fp);
}

if(!file_exists($configFile)){
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	echo 'Failed to create the settings file!';
	exit(3); // EXIT_CONFIG
}else{
	require_once $configFile;	
}

?>