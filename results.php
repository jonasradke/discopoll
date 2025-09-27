<?php
include_once 'config.php';

$poll_id = (int)$_GET['poll_id'];

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
