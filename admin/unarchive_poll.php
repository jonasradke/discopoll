<?php
include_once '../config.php';

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Get the poll ID from the query parameter
if (!isset($_GET['id'])) {
    die("Poll ID not specified.");
}

$pollId = (int) $_GET['id'];

// Unarchive the poll
$stmt = $pdo->prepare("UPDATE polls SET archived = 0 WHERE id = :id");
$stmt->execute([':id' => $pollId]);

// Redirect back to dashboard
header('Location: dashboard');
exit;
?>
