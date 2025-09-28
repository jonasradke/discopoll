<?php
include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $poll_id = $_GET['poll_id'] ?? null;
    
    if (!empty($poll_id)) {
        // Get user IP for tracking
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        try {
            // Get reaction counts for this poll
            $stmt = $pdo->prepare("
                SELECT reaction_type, COUNT(*) as count
                FROM poll_reactions 
                WHERE poll_id = :poll_id
                GROUP BY reaction_type
            ");
            $stmt->execute([':poll_id' => $poll_id]);
            $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user's reactions for this poll
            $stmt = $pdo->prepare("
                SELECT reaction_type
                FROM poll_reactions 
                WHERE poll_id = :poll_id AND ip_address = :ip_address
            ");
            $stmt->execute([
                ':poll_id' => $poll_id,
                ':ip_address' => $ip_address
            ]);
            $userReactions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Format response
            $response = [];
            foreach ($reactions as $reaction) {
                $response[] = [
                    'reaction' => $reaction['reaction_type'],
                    'count' => (int)$reaction['count'],
                    'user_reacted' => in_array($reaction['reaction_type'], $userReactions)
                ];
            }
            
            echo json_encode(['success' => true, 'reactions' => $response]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing poll_id']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
