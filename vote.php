<?php
include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poll_id   = $_POST['poll_id'] ?? null;
    $option_id = $_POST['option_id'] ?? null;

    if (!empty($poll_id) && !empty($option_id)) {
        // Check if the poll exists and is not archived
        $stmt = $pdo->prepare("SELECT id FROM polls WHERE id = :poll_id AND archived = 0");
        $stmt->execute([':poll_id' => $poll_id]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$poll) {
            echo "Poll not found or has been archived.";
            exit;
        }
        
        // Check if the user has already voted using a cookie
        if (isset($_COOKIE["voted_poll_$poll_id"])) {
            echo "You have already voted in this poll.";
            exit;
        }

        // Insert the vote into the database
        $stmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, voted_at) 
                               VALUES (:poll_id, :option_id, NOW())");
        $stmt->execute([
            ':poll_id'   => $poll_id,
            ':option_id' => $option_id
        ]);

        // Set a cookie to mark this poll as voted
        // Cookie expires after 30 days
        setcookie("voted_poll_$poll_id", '1', time() + (30 * 24 * 60 * 60), '/'); // Path '/' ensures it works site-wide

        echo "Vote recorded";
    } else {
        echo "Missing poll_id or option_id";
    }
}
