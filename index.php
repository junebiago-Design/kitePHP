<?php
// Redirect to the domain root, hiding the folder name
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
header('Location: ' . $protocol . '://' . $host . '/public');
exit;