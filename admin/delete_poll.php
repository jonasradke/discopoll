<?php
include_once '../config.php';

// Get the poll ID from the query parameter
if (!isset($_GET['id'])) {
    die("Poll ID not specified.");
}

$pollId = (int) $_GET['id'];

// Optional: you might also check if the poll exists here before deleting

// First delete votes related to the poll (if you don't have ON DELETE CASCADE in your DB)
$stmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = :poll_id");
$stmt->execute([':poll_id' => $pollId]);

// Then delete options related to the poll
$stmt = $pdo->prepare("DELETE FROM poll_options WHERE poll_id = :poll_id");
$stmt->execute([':poll_id' => $pollId]);

// Finally, delete the poll itself
$stmt = $pdo->prepare("DELETE FROM polls WHERE id = :id");
$stmt->execute([':id' => $pollId]);

// Redirect back to dashboard
header('Location: dashboard');
exit;
