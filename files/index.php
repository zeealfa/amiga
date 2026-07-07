<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
			
<html lang="en">

<head>
	<title>Test_AmigaSource.com - Since 2001... Your BEST source for Amiga information... Again</title>
	<link rel="icon" type="image/x-icon" href="/web_images/static/favicon.ico">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="robots" content="noindex,nofollow">
	<style type="text/css"> a { color: #006699; text-decoration: none; } </style>
	<style type="text/css"> body { background-image: url('/web_images/static/lg_boing.jpg'); }</style>
</head>

<body>

<?php

	define("ABS_PATH", dirname(__FILE__));
	
	if(!isset($_SESSION)){
		session_start();
	}
	include 'login_db.php';

		$_SESSION["content_type"]='news';

	include 'page_builder.php';
?>

</body>

</html>