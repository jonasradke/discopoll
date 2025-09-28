<?php
include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poll_id = $_POST['poll_id'] ?? null;
    $reaction = $_POST['reaction'] ?? null;
    
    if (!empty($poll_id) && !empty($reaction)) {
        // Get user IP for tracking
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        try {
            // Remove reaction
            $stmt = $pdo->prepare("
                DELETE FROM poll_reactions 
                WHERE poll_id = :poll_id AND reaction_type = :reaction AND ip_address = :ip_address
            ");
            $stmt->execute([
                ':poll_id' => $poll_id,
                ':reaction' => $reaction,
                ':ip_address' => $ip_address
            ]);
            
            echo json_encode(['success' => true]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
