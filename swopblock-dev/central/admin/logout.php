<?php
require_once dirname(__DIR__) . '/includes/auth.php';
logout_admin();
header('Location: login.php');
exit;
