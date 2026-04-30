<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$username = $_SESSION['faculty_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'Faculty'; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgoMeal - Nutritional Management System</title>
    <?php
    // Determine path prefix based on file location
    $path_prefix = (strpos($_SERVER['PHP_SELF'], 'pages/') !== false) ? '../../' : '';
    ?>
    <link rel="icon" type="image/svg+xml" href="<?php echo $path_prefix; ?>assets/Algomeal.svg">
    <!-- Material Font: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/modals_lite.css?v=1.1">
    <script src="<?php echo $path_prefix; ?>assets/js/modals_lite.js?v=1.1"></script>
    <style>
        :root {
            --bg-color: #f0f2f5;
            --surface: #ffffff;
            --primary: #0061ff;
            /* Modern Vibrant Blue */
            --primary-hover: #0052d9;
            --secondary: #ebf3ff;
            --text-main: #1a1f36;
            /* Deep UI Slate */
            --text-muted: #697386;
            --border: #e3e8ee;
            --success: #059669;
            /* Emeral Green */
            --warning: #d97706;
            --error: #dc2626;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Fixed Sidebar Navigation */
        .sidebar {
            width: 250px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header .btn-text {
            color: #000000;
            /* Black Hamburger */
            margin-right: 0.75rem;
            background: none;
            border: none;
            cursor: default;
            /* Non-interactive but preserved layout */
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
            display: inline-block;
            margin-right: 0.75rem;
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--text-main);
            white-space: nowrap;
        }

        .sidebar-title strong {
            font-weight: 700;
        }

        .nav-list {
            list-style: none;
            padding: 1rem 0;
            flex-grow: 1;
            width: 100%;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
            width: 100%;
            padding: 0 0.75rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 24px;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
            height: 48px;
        }

        .nav-link:hover {
            background-color: #f1f3f4;
            color: var(--text-main);
        }

        .nav-link.active {
            background-color: var(--secondary);
            color: var(--primary);
        }

        .nav-icon {
            font-size: 24px;
            margin-right: 1rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-text {
            font-size: 0.95rem;
        }
        
        .nav-header {
            padding: 1.5rem 1.75rem 0.5rem;
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-logout {
            margin-top: 0;
            border-top: none;
            padding-bottom: 1rem;
        }

        /* Sidebar User Profile */
        .sidebar-user {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-user-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .sidebar-user-role {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            display: flex;
            flex-direction: column;
            width: calc(100% - 250px);
            min-height: 100vh;
        }

        .topbar {
            display: none;
        }

        /* Dashboard Content */
        .content {
            padding: 2rem;
            flex-grow: 1;
        }

        /* Form & Cards global styles */
        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            transition: transform 0.2s ease;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.75rem 1rem;
            padding-top: 0;
            border-bottom: 1px solid var(--border);
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: var(--secondary);
            color: var(--primary);
            display: inline-block;
        }

        .badge.warning {
            background-color: #fef7e0;
            color: var(--warning);
        }

        .badge.success {
            background-color: var(--success);
            color: white;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn-text {
            color: var(--primary);
            background: none;
            border: none;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }

        /* Layout Grid */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Modal Sub-system */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--surface);
            border-radius: 8px;
            padding: 1.5rem;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 11px 15px -7px rgba(0, 0, 0, 0.2), 0 24px 38px 3px rgba(0, 0, 0, 0.14), 0 9px 46px 8px rgba(0, 0, 0, 0.12);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .modal-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .btn-cancel {
            color: var(--primary);
            background: transparent;
            border: none;
            padding: 0.5rem 1rem;
            text-transform: uppercase;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background-color: rgba(26, 115, 232, 0.04);
        }

        .btn-confirm {
            background-color: #d93025;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-confirm:hover {
            background-color: #c5221f;
        }

        /* PREMIUM UI SYSTEM (GLOBAL) */
        .btn-m3 {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-m3:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-m3:active {
            transform: scale(0.98);
        }

        .btn-m3-primary { background: var(--primary); color: white; }
        .btn-m3-primary:hover { background: var(--primary-hover); }

        .btn-m3-outline { 
            background: white; 
            color: var(--text-main); 
            border: 1px solid var(--border); 
            box-shadow: none; 
        }
        .btn-m3-outline:hover { 
            background: #f8fafc; 
            border-color: var(--primary); 
            color: var(--primary); 
        }

        .btn-m3-tonal { 
            background: var(--secondary); 
            color: var(--primary); 
            box-shadow: none; 
        }
        .btn-m3-tonal:hover { background: #d1e3ff; }

        .btn-m3-danger { 
            background: #fee2e2; 
            color: #b91c1c; 
            box-shadow: none; 
        }
        .btn-m3-danger:hover { background: #fecaca; }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* CORE DASHBOARD COMPONENTS */
        .mgmt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
        }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .kpi-card::after {
            content: 'Strategic Data';
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 0.55rem;
            font-weight: 800;
            color: var(--primary);
            opacity: 0.2;
            text-transform: uppercase;
        }

        .kpi-card.warning::after {
            color: var(--error);
            content: 'Low';
        }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--text-main);
        }

        .kpi-subtext {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
            .kpi-row { grid-template-columns: 1fr; }
        }

        .dashboard-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0;
            color: var(--text-main);
        }

        .logging-form {
            background: #f8fafc;
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-field label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        .form-field input, .form-field select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-weight: 600;
        }

        .reports-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .reports-table th {
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .reports-table td {
            padding: 1rem;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.825rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* GLOBAL PRINT MANAGEMENT */
        @media print {
            .sidebar, .topbar, .no-print, .modal-overlay, .mgmt-tabs, .tab-btn {
                display: none !important;
            }
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                left: 0 !important;
                position: static !important;
            }
            .content {
                padding: 0 !important;
                margin: 0 !important;
            }
            body {
                background: white !important;
                overflow: visible !important;
            }
            .dashboard-card {
                border: 1px solid #eee !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>