<?php
include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poll_id   = $_POST['poll_id'] ?? null;
    $option_id = $_POST['option_id'] ?? null;

    if (!empty($poll_id) && !empty($option_id)) {
        // Insert vote
        $stmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, voted_at) 
                               VALUES (:poll_id, :option_id, NOW())");
        $stmt->execute([
            ':poll_id'   => $poll_id,
            ':option_id' => $option_id
        ]);

        // Mark this poll as "voted" in the session
        if (!isset($_SESSION['voted_polls'])) {
            $_SESSION['voted_polls'] = [];
        }
        $_SESSION['voted_polls'][$poll_id] = true;

        echo "Vote recorded";
    } else {
        echo "Missing poll_id or option_id";
    }
}
