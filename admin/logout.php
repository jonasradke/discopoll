<?php
include_once '../config.php';
session_unset();
session_destroy();
header('Location: login');
exit;
