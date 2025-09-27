<?php
include_once '../config.php';

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Get the poll ID and action from the query parameters
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    die("Poll ID or action not specified.");
}

$pollId = (int) $_GET['id'];
$action = $_GET['action'];

// Validate action
if (!in_array($action, ['archive', 'unarchive'])) {
    die("Invalid action specified.");
}

// Check if poll exists
$stmt = $pdo->prepare("SELECT id FROM polls WHERE id = :id");
$stmt->execute([':id' => $pollId]);
if (!$stmt->fetch()) {
    die("Poll not found.");
}

// Update the archived status
$archivedValue = ($action === 'archive') ? 1 : 0;
$stmt = $pdo->prepare("UPDATE polls SET archived = :archived WHERE id = :id");
$stmt->execute([
    ':archived' => $archivedValue,
    ':id' => $pollId
]);

// Set success message in session
$_SESSION['success_message'] = $action === 'archive' 
    ? 'Umfrage wurde erfolgreich archiviert.' 
    : 'Umfrage wurde erfolgreich aktiviert.';

// Redirect back to dashboard
header('Location: dashboard');
exit;
?>
