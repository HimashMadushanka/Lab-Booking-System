<?php
session_start();
require '../db.php';
require '../fpdf/fpdf.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$type = $_GET['type'] ?? 'bookings';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d');      // Current date

// Validate dates
if (!strtotime($date_from) || !strtotime($date_to)) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
}

// Ensure date_from is not after date_to
if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

switch($type) {
    case 'bookings':
        generateBookingsPDF($date_from, $date_to);
        break;
    case 'users':
        generateUsersPDF($date_from, $date_to);
        break;
    case 'analytics':
        generateAnalyticsPDF($date_from, $date_to);
        break;
    default:
        generateBookingsPDF($date_from, $date_to);
}

function generateBookingsPDF($date_from, $date_to) {
    global $conn;
    
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header with date range
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'LAB BOOKINGS REPORT',0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,10,'Period: '.date('M j, Y', strtotime($date_from)).' to '.date('M j, Y', strtotime($date_to)),0,1,'C');
    $pdf->Ln(5);
    
    // Get bookings data for the date range
    $result = $conn->query("
        SELECT b.*, u.name as user_name, c.code as computer_code, l.name as lab_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN computers c ON b.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        WHERE b.date BETWEEN '$date_from' AND '$date_to'
        ORDER BY b.date DESC, b.start_time DESC
    ");
    
    // Check if there are any bookings in this period
    if ($result->num_rows == 0) {
        $pdf->SetFont('Arial','I',12);
        $pdf->Cell(0,10,'No bookings found for the selected period.',0,1,'C');
        $pdf->Output('I', 'bookings_report_'.$date_from.'_to_'.$date_to.'.pdf');
        return;
    }
    
    // Table header
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(200,220,255);
    $pdf->Cell(15,10,'ID',1,0,'C',true);
    $pdf->Cell(40,10,'User',1,0,'C',true);
    $pdf->Cell(30,10,'Lab',1,0,'C',true);
    $pdf->Cell(25,10,'Computer',1,0,'C',true);
    $pdf->Cell(25,10,'Date',1,0,'C',true);
    $pdf->Cell(40,10,'Time',1,0,'C',true);
    $pdf->Cell(25,10,'Status',1,1,'C',true);
    
    // Table data
    $pdf->SetFont('Arial','',9);
    $fill = false;
    while($row = $result->fetch_assoc()) {
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        
        $pdf->Cell(15,8,$row['id'],1,0,'C',$fill);
        $pdf->Cell(40,8,substr($row['user_name'],0,18),1,0,'L',$fill);
        $pdf->Cell(30,8,substr($row['lab_name'],0,12),1,0,'L',$fill);
        $pdf->Cell(25,8,$row['computer_code'],1,0,'C',$fill);
        $pdf->Cell(25,8,date('M d',strtotime($row['date'])),1,0,'C',$fill);
        $pdf->Cell(40,8,date('g:i A',strtotime($row['start_time'])).' - '.date('g:i A',strtotime($row['end_time'])),1,0,'C',$fill);
        
        // Status with color
        $status_color = $row['status'] == 'approved' ? [0,128,0] : 
                       ($row['status'] == 'pending' ? [255,165,0] : [255,0,0]);
        $pdf->SetTextColor($status_color[0], $status_color[1], $status_color[2]);
        $pdf->Cell(25,8,ucfirst($row['status']),1,1,'C',$fill);
        $pdf->SetTextColor(0,0,0);
        
        $fill = !$fill;
    }
    
    // Summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Summary Statistics:',0,1);
    $pdf->SetFont('Arial','',10);
    
    $total = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $approved = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='approved' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $pending = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='pending' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $rejected = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='rejected' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    
    $pdf->Cell(0,6,"Total Bookings: $total",0,1);
    $pdf->Cell(0,6,"Approved: $approved",0,1);
    $pdf->Cell(0,6,"Pending: $pending",0,1);
    $pdf->Cell(0,6,"Rejected: $rejected",0,1);
    
    if ($total > 0) {
        $pdf->Cell(0,6,"Approval Rate: ".round(($approved/$total)*100,1)."%",0,1);
    }
    
    $pdf->Cell(0,6,"Generated on: ".date('Y-m-d H:i:s'),0,1);
    
    $pdf->Output('I', 'bookings_report_'.$date_from.'_to_'.$date_to.'.pdf');
}

function generateUsersPDF($date_from, $date_to) {
    global $conn;
    
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header with date range
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'USERS REPORT',0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,10,'Active Period: '.date('M j, Y', strtotime($date_from)).' to '.date('M j, Y', strtotime($date_to)),0,1,'C');
    $pdf->Ln(5);
    
    // Get users data with booking counts for the period
    $result = $conn->query("
        SELECT u.*, 
               COUNT(b.id) as total_bookings,
               SUM(CASE WHEN b.status='approved' THEN 1 ELSE 0 END) as approved_bookings
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id AND b.date BETWEEN '$date_from' AND '$date_to'
        GROUP BY u.id
        ORDER BY total_bookings DESC
    ");
    
    // Table header
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(200,220,255);
    $pdf->Cell(15,10,'ID',1,0,'C',true);
    $pdf->Cell(50,10,'Name',1,0,'C',true);
    $pdf->Cell(70,10,'Email',1,0,'C',true);
    $pdf->Cell(25,10,'Role',1,0,'C',true);
    $pdf->Cell(30,10,'Bookings',1,1,'C',true);
    
    // Table data
    $pdf->SetFont('Arial','',9);
    $fill = false;
    while($row = $result->fetch_assoc()) {
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        
        $pdf->Cell(15,8,$row['id'],1,0,'C',$fill);
        $pdf->Cell(50,8,substr($row['name'],0,25),1,0,'L',$fill);
        $pdf->Cell(70,8,substr($row['email'],0,35),1,0,'L',$fill);
        $pdf->Cell(25,8,ucfirst($row['role']),1,0,'C',$fill);
        $pdf->Cell(30,8,$row['total_bookings'],1,1,'C',$fill);
        
        $fill = !$fill;
    }
    
    // Summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Summary:',0,1);
    $pdf->SetFont('Arial','',10);
    
    $total_users = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
    $active_users = $conn->query("SELECT COUNT(DISTINCT user_id) as cnt FROM bookings WHERE date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $total_admins = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='admin'")->fetch_assoc()['cnt'];
    
    $pdf->Cell(0,6,"Total Users: $total_users",0,1);
    $pdf->Cell(0,6,"Active Users (this period): $active_users",0,1);
    $pdf->Cell(0,6,"Administrators: $total_admins",0,1);
    $pdf->Cell(0,6,"Generated on: ".date('Y-m-d H:i:s'),0,1);
    
    $pdf->Output('I', 'users_report_'.$date_from.'_to_'.$date_to.'.pdf');
}

function generateAnalyticsPDF($date_from, $date_to) {
    global $conn;
    
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header with date range
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'ANALYTICS REPORT',0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,10,'Period: '.date('M j, Y', strtotime($date_from)).' to '.date('M j, Y', strtotime($date_to)),0,1,'C');
    $pdf->Ln(5);
    
    // Overall Statistics
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Overall Statistics',0,1);
    $pdf->SetFont('Arial','',10);
    
    $total_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $approved = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='approved' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $pending = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='pending' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    $rejected = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='rejected' AND date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
    
    $pdf->Cell(0,6,"Total Bookings: $total_bookings",0,1);
    
    if ($total_bookings > 0) {
        $pdf->Cell(0,6,"Approved: $approved (".round(($approved/$total_bookings)*100,1)."%)",0,1);
        $pdf->Cell(0,6,"Pending: $pending (".round(($pending/$total_bookings)*100,1)."%)",0,1);
        $pdf->Cell(0,6,"Rejected: $rejected (".round(($rejected/$total_bookings)*100,1)."%)",0,1);
    }
    
    $pdf->Ln(5);
    
    // Lab Usage
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Lab Usage Ranking',0,1);
    $pdf->SetFont('Arial','',10);
    
    $labs = $conn->query("
        SELECT l.name, COUNT(b.id) as bookings_count
        FROM labs l
        LEFT JOIN computers c ON l.id = c.lab_id
        LEFT JOIN bookings b ON c.id = b.computer_id AND b.date BETWEEN '$date_from' AND '$date_to'
        GROUP BY l.id, l.name
        ORDER BY bookings_count DESC
    ");
    
    $rank = 1;
    while($lab = $labs->fetch_assoc()) {
        $percentage = $total_bookings > 0 ? round(($lab['bookings_count']/$total_bookings)*100,1) : 0;
        $pdf->Cell(0,6,"$rank. {$lab['name']}: {$lab['bookings_count']} bookings ($percentage%)",0,1);
        $rank++;
    }
    
    $pdf->Ln(5);
    
    // Peak Hours
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Peak Booking Hours',0,1);
    $pdf->SetFont('Arial','',10);
    
    $hours = $conn->query("
        SELECT HOUR(start_time) as hour, COUNT(*) as count
        FROM bookings 
        WHERE date BETWEEN '$date_from' AND '$date_to' AND status = 'approved'
        GROUP BY HOUR(start_time)
        ORDER BY count DESC
        LIMIT 5
    ");
    
    $rank = 1;
    while($hour = $hours->fetch_assoc()) {
        $time = $hour['hour'] < 12 ? $hour['hour'].':00 AM' : 
               ($hour['hour'] == 12 ? '12:00 PM' : ($hour['hour']-12).':00 PM');
        $pdf->Cell(0,6,"$rank. $time: {$hour['count']} bookings",0,1);
        $rank++;
    }
    
    $pdf->Ln(5);
    $pdf->Cell(0,6,"Generated on: ".date('Y-m-d H:i:s'),0,1);
    
    $pdf->Output('I', 'analytics_report_'.$date_from.'_to_'.$date_to.'.pdf');
}
?>
