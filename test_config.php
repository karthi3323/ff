<?php
require_once "config/constants.php";
echo "<h2>Configuration Test</h2>";
echo "Server from sysconfig.txt: " . DIR_WS_SERVER . "<br>";
echo "Base URL: " . BASE_URL . "<br>";
echo "Assets URL: " . ASSETS_URL . "<br>";
echo "Current file: " . __FILE__ . "<br>";

// Test if assets are accessible
echo "<h3>Asset Test:</h3>";
echo "CSS URL: " . ASSETS_URL . "/css/style.css<br>";
echo "JS URL: " . ASSETS_URL . "/js/script.js<br>";
?>