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
    <style>
        :root {
            --bg-color: #f0f2f5;
            --surface: #ffffff;
            --primary: #0061ff; /* Modern Vibrant Blue */
            --primary-hover: #0052d9;
            --secondary: #ebf3ff;
            --text-main: #1a1f36; /* Deep UI Slate */
            --text-muted: #697386;
            --border: #e3e8ee;
            --success: #059669; /* Emeral Green */
            --warning: #d97706;
            --error: #dc2626;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
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

        /* Sidebar Navigation */
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
            z-index: 100;
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            display: inline-block;
            margin-right: 1rem;
        }

        .sidebar-title {
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--text-main);
        }
        .sidebar-title strong {
            font-weight: 700;
        }

        .nav-list {
            list-style: none;
            padding: 1rem 0;
            flex-grow: 1;
        }

        .nav-item {
            padding: 0 1rem;
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 0 24px 24px 0;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
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
            margin-right: 1rem;
            font-size: 20px;
        }

        .nav-logout {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid var(--border);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            display: flex;
            flex-direction: column;
            width: calc(100% - 250px);
        }

        /* Top App Bar */
        .topbar {
            background-color: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 400;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--secondary);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
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

        .btn-text:hover {
            background-color: rgba(26, 115, 232, 0.04);
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
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal {
            background-color: var(--surface);
            border-radius: 8px;
            padding: 1.5rem;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 11px 15px -7px rgba(0,0,0,0.2), 0 24px 38px 3px rgba(0,0,0,0.14), 0 9px 46px 8px rgba(0,0,0,0.12);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal { transform: translateY(0); }
        .modal-title { font-size: 1.25rem; font-weight: 500; margin-bottom: 1rem; color: var(--text-main); }
        .modal-text { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
        .btn-cancel {
            color: var(--primary); background: transparent; border: none; padding: 0.5rem 1rem;
            text-transform: uppercase; font-weight: 500; cursor: pointer;
        }
        .btn-cancel:hover { background-color: rgba(26,115,232,0.04); }
        .btn-confirm {
            background-color: #d93025; color: #fff; border: none; padding: 0.5rem 1rem;
            border-radius: 4px; text-transform: uppercase; font-weight: 500; cursor: pointer; text-decoration: none;
        }
        .btn-confirm:hover { background-color: #c5221f; }
    </style>
</head>
<body>
