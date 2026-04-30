<?php
require_once 'db.php';
session_destroy();
header('Location: auth.php?tab=login');
exit;
