<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
logout_user();
header('Location: /CoachFee/index.php');
exit;
