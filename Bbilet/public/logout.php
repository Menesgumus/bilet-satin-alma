<?php
declare(strict_types=1);

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();
session_unset();
session_destroy();
header('Location: /index.php');
exit;


