<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$default_from = date('Y-m-01'); // First day of current month
$default_to = date('Y-m-d');    // Today
?>
<!DOCTYPE html>
<html>
<head>
    <title>PDF Report Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:  #b8eaf8ff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            top: -300px;
            right: -300px;
            animation: float 25s infinite ease-in-out;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -250px;
            left: -250px;
            animation: float 20s infinite ease-in-out reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(40px, -40px) scale(1.1); }
            66% { transform: translate(-30px, 30px) scale(0.9); }
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 55px 45px;
            border-radius: 28px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.3) inset;
            max-width: 560px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1);
            margin-left: 150px;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .header-icon {
            width: 85px;
            height: 85px;
            background:  #2980b9;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 42px;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
            position: relative;
        }
        
        .header-icon::before {
            content: '';
            position: absolute;
            inset: -3px;
            background:  #2980b9;
            border-radius: 25px;
            opacity: 0;
            animation: glow 3s infinite ease-in-out;
            z-index: -1;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.08) rotate(2deg); }
        }
        
        @keyframes glow {
            0%, 100% { opacity: 0; filter: blur(10px); }
            50% { opacity: 0.6; filter: blur(15px); }
        }
        
        h2 {
            text-align: center;
            background:  #2980b9;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 45px;
            font-size: 15px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 28px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            letter-spacing: 0.2px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            pointer-events: none;
            font-size: 18px;
        }
        
        select, input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            color: #2d3748;
            font-weight: 500;
        }
        
        select:hover, input:hover {
            background: white;
            border-color: #cbd5e0;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }
        
        input[type="date"] {
            padding-left: 48px;
        }
        
        button {
            width: 100%;
            padding: 17px;
            background:  #2980b9;
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        button:hover::before {
            left: 100%;
        }
        
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5);
        }
        
        button:active {
            transform: translateY(-1px);
        }
        
        .report-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .report-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            color: #4a5568;
            font-weight: 500;
        }
        
        .report-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .report-option.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .quick-dates {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }
        
        .quick-date {
            flex: 1;
            padding: 12px 10px;
            background: #fafafa;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
        }
        
        .quick-date:hover {
            background: white;
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .quick-date:active {
            transform: translateY(-1px);
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
        
        .back-link a {
            background: #2980b9;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .back-link a:hover {
            gap: 10px;
            opacity: 0.8;
        }
        
        .date-hint {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 6px;
            font-style: italic;
        }
        
        @media (max-width: 600px) {
            .form-container {
                padding: 45px 35px;
            }
            
            h2 {
                font-size: 28px;
            }
            
            .header-icon {
                width: 75px;
                height: 75px;
                font-size: 38px;
            }
            
            .quick-dates {
                flex-wrap: wrap;
            }
            
            .quick-date {
                flex-basis: calc(50% - 6px);
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header-icon">📊</div>
        <h2>Generate PDF Report</h2>
        <p class="subtitle">Create detailed reports for your lab management system</p>
        
        <form method="GET" action="generate_pdf.php">
            <div class="form-group">
                <label for="type">📋 Report Type:</label>
                <select name="type" id="type" required>
                    <option value="bookings">Bookings Report</option>
                    <option value="users">Users Report</option>
                    <option value="analytics">Analytics Report</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date_from">📅 From Date:</label>
                <div class="input-wrapper">
                    <span class="input-icon">📅</span>
                    <input type="date" name="date_from" id="date_from" value="<?= $default_from ?>" required>
                </div>
                <p class="date-hint">Select the start date of your report period</p>
            </div>
            
            <div class="form-group">
                <label for="date_to">📅 To Date:</label>
                <div class="input-wrapper">
                    <span class="input-icon">📅</span>
                    <input type="date" name="date_to" id="date_to" value="<?= $default_to ?>" required>
                </div>
                <p class="date-hint">Select the end date of your report period</p>
            </div>
            
            <div class="quick-dates">
                <button type="button" class="quick-date" onclick="setDateRange('today')">Today</button>
                <button type="button" class="quick-date" onclick="setDateRange('week')">This Week</button>
                <button type="button" class="quick-date" onclick="setDateRange('month')">This Month</button>
                <button type="button" class="quick-date" onclick="setDateRange('year')">This Year</button>
            </div>
            
            <button type="submit">🚀 Generate PDF Report</button>
        </form>
        
        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        function setDateRange(range) {
            const today = new Date();
            let fromDate = new Date();
            
            switch(range) {
                case 'today':
                    fromDate = today;
                    break;
                case 'week':
                    fromDate.setDate(today.getDate() - 7);
                    break;
                case 'month':
                    fromDate.setDate(1); // First day of current month
                    break;
                case 'year':
                    fromDate = new Date(today.getFullYear(), 0, 1); // First day of year
                    break;
            }
            
            document.getElementById('date_from').value = formatDate(fromDate);
            document.getElementById('date_to').value = formatDate(today);
        }
        
        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }
        
        // Set default to current month on page load
        document.addEventListener('DOMContentLoaded', function() {
            setDateRange('month');
        });
    </script>
</body>
</html>