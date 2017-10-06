<?php

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

define('COOKIE_NAME','kp_59d3b35ef3156');

/*

|---------------------------------------------------------

| App settings

|---------------------------------------------------------

*/

Define('DB_HOST',	'localhost');

define('BD_DRIVER',	'mysql');

Define('DB_NAME',	'postador_postador');

Define('DB_USER',	'postador_gil');

Define('DB_PASS',	'112233daia!');

/*

|---------------------------------------------------------

| App URL must be difened

|---------------------------------------------------------

*/

Define('BASE_URL',	'https://postadorcombr.herokuapp.com/');

/*

|---------------------------------------------------------

| Index File

|---------------------------------------------------------

*/

Define('INDEX_PAGE', 'index.php');

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

Define('APP_TOKEN', '01965a94f6bbf88126c6ac3bfc2388f0');

/*

|---------------------------------------------------------

| Sys installed

|---------------------------------------------------------

*/

Define('SYS_INSTALLED',	TRUE);

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

?>
