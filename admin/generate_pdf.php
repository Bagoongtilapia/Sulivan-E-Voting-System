<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'E-VOTE Election Results', 0, true, 'C', 0, '', 0, false, 'M', 'M');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'Generated on: ' . date('F d, Y h:i A'), 0, true, 'C');
        $this->Line(15, $this->GetY(), $this->GetPageWidth() - 15, $this->GetY());
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

try {
    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('E-VOTE System');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Election Results Report');

    // Set margins
    $pdf->SetMargins(15, 40, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set default font subsetting mode
    $pdf->setFontSubsetting(true);

    // Add a page
    $pdf->AddPage();

    // Get election status
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';

    // Fetch election name from the database
    try {
        $stmt = $pdo->query("SELECT election_name FROM election_status ORDER BY id DESC LIMIT 1");
        $electionName = $stmt->fetchColumn() ?: 'SSLG ELECTION 2025';
    } catch (PDOException $e) {
        $electionName = 'SSLG ELECTION 2025';
    }

    // Status header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($electionStatus === 'Ended' ? 40 : 200, $electionStatus === 'Ended' ? 167 : 150, $electionStatus === 'Ended' ? 69 : 0);
    $pdf->Cell(0, 10, 'Election Status: ' . $electionStatus, 0, 1, 'R');
    $pdf->SetTextColor(0);

    // Get total voters
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
    $totalVoters = $stmt->fetch(PDO::FETCH_COLUMN);

    // Get total votes cast (using student_id)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM votes");
    $totalVotesCast = $stmt->fetch(PDO::FETCH_COLUMN);

    // Calculate turnout
    $turnout = $totalVoters > 0 ? round(($totalVotesCast / $totalVoters) * 100, 1) : 0;

    // Add statistics section
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Voting Statistics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(60, 8, 'Total Eligible Voters:', 0, 0);
    $pdf->Cell(0, 8, number_format($totalVoters), 0, 1);
    $pdf->Cell(60, 8, 'Total Votes Cast:', 0, 0);
    $pdf->Cell(0, 8, number_format($totalVotesCast), 0, 1);
    $pdf->Cell(60, 8, 'Voter Turnout:', 0, 0);
    $pdf->Cell(0, 8, $turnout . '%', 0, 1);
    $pdf->Ln(5);

    // Get results by position
    $stmt = $pdo->query("
        WITH RankedCandidates AS (
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
        SELECT *
        FROM RankedCandidates
        ORDER BY position_name, rank
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group results by position
    $positions = [];
    foreach ($results as $row) {
        if (!isset($positions[$row['position_id']])) {
            $positions[$row['position_id']] = [
                'name' => $row['position_name'],
                'candidates' => []
            ];
        }
        $positions[$row['position_id']]['candidates'][] = [
            'name' => $row['candidate_name'],
            'votes' => $row['vote_count'],
            'rank' => $row['rank']
        ];
    }

    // Output each position's results
    foreach ($positions as $position) {
        // Add new page if not enough space
        if ($pdf->GetY() > $pdf->GetPageHeight() - 60) {
            $pdf->AddPage();
        }

        // Position header
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetFillColor(51, 122, 183);
        $pdf->SetTextColor(255);
        $pdf->Cell(0, 10, $position['name'], 0, 1, 'L', true);
        $pdf->SetTextColor(0);

        // Table header
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(15, 8, 'Rank', 1, 0, 'C', true);
        $pdf->Cell(100, 8, 'Candidate Name', 1, 0, 'L', true);
        $pdf->Cell(35, 8, 'Votes', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'Percentage', 1, 1, 'C', true);

        // Output candidates
        $pdf->SetFont('helvetica', '', 11);
        foreach ($position['candidates'] as $candidate) {
            $percentage = $totalVoters > 0 ? round(($candidate['votes'] / $totalVoters) * 100, 1) : 0;
            
            // Highlight winner
            if ($candidate['rank'] == 1) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetFillColor(223, 240, 216);
            } else {
                $pdf->SetFont('helvetica', '', 11);
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->Cell(15, 8, $candidate['rank'], 1, 0, 'C', true);
            $pdf->Cell(100, 8, $candidate['name'] . ($candidate['rank'] == 1 ? ' (Winner)' : ''), 1, 0, 'L', true);
            $pdf->Cell(35, 8, number_format($candidate['votes']), 1, 0, 'C', true);
            $pdf->Cell(35, 8, $percentage . '%', 1, 1, 'C', true);
        }

        $pdf->Ln(5);
    }

    // Output PDF
    $pdf->Output('Election_Results_' . date('Y-m-d_His') . '.pdf', 'D');

} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}
