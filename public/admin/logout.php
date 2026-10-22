<?php
require_once __DIR__ . '/inc/auth.php';

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;
