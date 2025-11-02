<?php
// show local server IP to use in Arduino
$hostname = gethostname();
$localIP = gethostbyname($hostname);

echo "<h2>Local Server Info</h2>";
echo "<b>Computer Name:</b> $hostname<br>";
echo "<b>Local IP Address:</b> $localIP<br><br>";
echo "Use this IP in your Arduino code as:<br>";
echo "<code>IPAddress server(" . str_replace('.', ',', $localIP) . ");</code>";
?>
