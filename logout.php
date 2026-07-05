<?php
require_once "config/constants.php";

session_start();
session_destroy();
header("Location: " . BASE_URL . "index.php");
exit();
?>