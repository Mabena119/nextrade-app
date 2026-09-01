<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/includes/db-config.php';

$con = auraai_db_connect();
