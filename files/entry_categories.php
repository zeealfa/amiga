<?php
	if(!isset($_SESSION)){
		session_start();
	}
	$_SESSION["content_type"]='categories';
	include 'login_db.php';
	include ("page_builder.php");
?>
