<?php

require_once __DIR__ . '/config.php';
include('class.db.php');
include('classes.php');
$admin = new Admin(DB_HOST,DB_USER,DB_PASS,DB_NAME);

?>