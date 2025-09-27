<?php
include_once '../config.php';

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get poll ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Poll ID required']);
    exit;
}

$pollId = (int)$_GET['id'];

// Fetch poll details
$stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id");
$stmt->execute([':id' => $pollId]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    http_response_code(404);
    echo json_encode(['error' => 'Poll not found']);
    exit;
}

// Fetch poll options with vote counts
$stmtOptions = $pdo->prepare("
    SELECT po.id, po.option_text, COUNT(pv.id) as votes
    FROM poll_options po
    LEFT JOIN poll_votes pv ON pv.option_id = po.id
    WHERE po.poll_id = :poll_id
    GROUP BY po.id, po.option_text
    ORDER BY po.id ASC
");
$stmtOptions->execute([':poll_id' => $pollId]);
$options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

// Calculate total votes
$totalVotes = array_sum(array_column($options, 'votes'));

// Get recent votes for activity log
$stmtRecentVotes = $pdo->prepare("
    SELECT pv.voted_at, po.option_text
    FROM poll_votes pv
    JOIN poll_options po ON pv.option_id = po.id
    WHERE pv.poll_id = :poll_id
    ORDER BY pv.voted_at DESC
    LIMIT 10
");
$stmtRecentVotes->execute([':poll_id' => $pollId]);
$recentVotes = $stmtRecentVotes->fetchAll(PDO::FETCH_ASSOC);

// Find most popular option
$topOption = null;
if ($totalVotes > 0) {
    $topOption = array_reduce($options, function($max, $option) {
        return ($option['votes'] > $max['votes']) ? $option : $max;
    }, ['votes' => 0]);
}

// Prepare response
$response = [
    'poll' => $poll,
    'options' => $options,
    'totalVotes' => $totalVotes,
    'recentVotes' => $recentVotes,
    'topOption' => $topOption,
    'timestamp' => time()
];

header('Content-Type: application/json');
echo json_encode($response);
?>
