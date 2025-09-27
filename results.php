<?php
include_once 'config.php';

$poll_id = (int)$_GET['poll_id'];

// First check if the poll exists and is not archived
$stmtPoll = $pdo->prepare("SELECT id FROM polls WHERE id = :poll_id AND archived = 0");
$stmtPoll->execute([':poll_id' => $poll_id]);
$poll = $stmtPoll->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    // Return empty array if poll doesn't exist or is archived
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Fetch all options and count votes
$stmtOptions = $pdo->prepare("
    SELECT po.id, po.option_text, COUNT(pv.id) as votes
    FROM poll_options po
    LEFT JOIN poll_votes pv ON pv.option_id = po.id
    WHERE po.poll_id = :poll_id
    GROUP BY po.id
");
$stmtOptions->execute([':poll_id' => $poll_id]);
$options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
header('Content-Type: application/json');
echo json_encode($options);
