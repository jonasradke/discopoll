<?php
include_once '../config.php';

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "Not logged in";
    exit;
}

// Get poll ID
if (!isset($_GET['poll_id'])) {
    echo "No poll ID provided";
    exit;
}

$pollId = (int)$_GET['poll_id'];

try {
    // Test the query
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

    echo "<h3>Debug Info for Poll ID: $pollId</h3>";
    echo "<p>Number of votes found: " . count($recentVotes) . "</p>";
    echo "<pre>";
    print_r($recentVotes);
    echo "</pre>";

    // Also check if poll exists
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :poll_id");
    $stmt->execute([':poll_id' => $pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h4>Poll Info:</h4>";
    echo "<pre>";
    print_r($poll);
    echo "</pre>";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
