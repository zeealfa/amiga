<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='archived_sites';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
