<?php
session_start();
require_once '../config/database.php';

// Check if user is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit();
}

// Get election status
try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
} catch (PDOException $e) {
    header('Location: election_results.php?error=Error checking election status');
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="election_results_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, ['Position', 'Candidate', 'Votes', 'Percentage', 'Status']);

try {
    // Get total voters
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
    $totalVoters = $stmt->fetch(PDO::FETCH_COLUMN);

    // Get results
    $stmt = $pdo->query("
        WITH VoteCounts AS (
            SELECT 
                p.id as position_id,
                p.position_name,
                c.id as candidate_id,
                c.name as candidate_name,
                COUNT(v.id) as vote_count,
                ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY COUNT(v.id) DESC) as rank
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            LEFT JOIN votes v ON c.id = v.candidate_id
            GROUP BY p.id, p.position_name, c.id, c.name
        )
        SELECT 
            position_name,
            candidate_name,
            vote_count,
            rank
        FROM VoteCounts
        ORDER BY position_name, rank
    ");

    $currentPosition = null;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $percentage = $totalVoters > 0 ? ($row['vote_count'] / $totalVoters) * 100 : 0;
        $status = $row['rank'] === 1 && $electionStatus === 'Ended' ? 'Winner' : '';
        
        fputcsv($output, [
            $row['position_name'],
            $row['candidate_name'],
            $row['vote_count'],
            number_format($percentage, 1) . '%',
            $status
        ]);
    }
} catch (PDOException $e) {
    // If there's an error, write it to the CSV
    fputcsv($output, ['Error generating results']);
}

// Close the output stream
fclose($output);
