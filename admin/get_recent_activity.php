<?php
include_once '../config.php';

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit;
}

// Get poll ID
if (!isset($_GET['poll_id'])) {
    http_response_code(400);
    exit;
}

$pollId = (int)$_GET['poll_id'];

try {
    // Get recent votes for activity log
    $stmt = $pdo->prepare("
        SELECT pv.voted_at, po.option_text
        FROM poll_votes pv
        JOIN poll_options po ON pv.option_id = po.id
        WHERE pv.poll_id = :poll_id
        ORDER BY pv.voted_at DESC
        LIMIT 10
    ");
    $stmt->execute([':poll_id' => $pollId]);
    $recentVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON
    header('Content-Type: application/json');
    echo json_encode($recentVotes);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
