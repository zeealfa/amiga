<?php
require_once __DIR__ . '/config.php';

$myConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)
    or die('could not connect to mysql');

mysqli_select_db($myConnection, DB_NAME)
    or die('Could not select database!!!');
