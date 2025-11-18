<?php

use Api\Controller\CheckRoomController;

require_once '../database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Controller/CheckRoomController.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// Initialize and process the request
$roomNumber = $_GET['roomNumber'] ?? 0;

$controller = new CheckRoomController($conn, $roomNumber);
$controller();
