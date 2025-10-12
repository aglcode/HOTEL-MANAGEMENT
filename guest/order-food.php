<?php
include '../database.php';
$room = $_GET['room'] ?? '';
echo "<h2>🍔 Order Food - Room " . htmlspecialchars($room) . "</h2>";
echo "<p>Food ordering form coming soon...</p>";
