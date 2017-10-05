<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Error</title>
<style type="text/css">


body {
	background: #fff;
	text-align: center; 
	padding: 100px;
	font: 20px Helvetica, sans-serif; 
	color: #333; 
}


h1 {
	color: #444;
	background-color: transparent;
	font-size: 30px;
	font-weight: normal;
	margin: 0 0 14px 0;
	padding: 14px 15px 10px 15px;
}

</style>
</head>
<body>
	<div id="container">
		<h1>Oops! <?php echo $heading; ?></h1>
		<p><?php echo $message; ?></p>
	</div>
</body>
</html>