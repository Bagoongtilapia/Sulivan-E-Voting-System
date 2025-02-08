<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get election status
try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching election status']);
    exit();
}

try {
    // Get results by position
    $stmt = $pdo->query("
        SELECT 
            p.id as position_id,
            p.position_name,
            c.id as candidate_id,
            c.name as candidate_name,
            c.image_url,
            COUNT(v.id) as vote_count,
            (SELECT COUNT(*) FROM users WHERE role = 'Student') as total_voters,
            MAX(v.created_at) as last_vote_time
        FROM positions p
        LEFT JOIN candidates c ON p.id = c.position_id
        LEFT JOIN votes v ON c.id = v.candidate_id
        GROUP BY p.id, p.position_name, c.id, c.name, c.image_url
        ORDER BY p.position_name, vote_count DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total votes and last vote timestamp
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT student_id) as total_votes,
            MAX(created_at) as last_vote
        FROM votes
    ");
    $voteInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Organize results by position
    $positions = [];
    foreach ($results as $row) {
        if (!isset($positions[$row['position_id']])) {
            $positions[$row['position_id']] = [
                'name' => $row['position_name'],
                'candidates' => [],
                'total_voters' => $row['total_voters']
            ];
        }
        if ($row['candidate_id']) {
            $positions[$row['position_id']]['candidates'][] = [
                'id' => $row['candidate_id'],
                'name' => $row['candidate_name'],
                'image_url' => $row['image_url'],
                'votes' => $row['vote_count']
            ];
        }
    }

    // Prepare response
    $response = [
        'status' => $electionStatus,
        'positions' => array_values($positions),
        'total_votes' => $voteInfo['total_votes'],
        'last_vote_time' => $voteInfo['last_vote'],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching election results']);
}
