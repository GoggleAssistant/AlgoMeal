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
    <!-- Material Font: Roboto & Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/modals_lite.css?v=1.1">
    <script src="<?php echo $path_prefix; ?>assets/js/modals_lite.js?v=1.1"></script>
    <style>
        :root {
            --bg-color: #f6f8fa;
            --surface: #ffffff;
            --primary: #0061ff;
            --primary-hover: #0052d9;
            --secondary: #ebf3ff;
            --text-main: #1a1f36;
            --text-muted: #697386;
            --border: #e3e8ee;
            --success: #059669;
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
            font-family: 'Outfit', 'Roboto', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Fixed Sidebar Navigation */
        .sidebar {
            width: 260px;
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

        .brand-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            margin-right: 0.75rem;
        }

        .sidebar-title {
            font-size: 1.3rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .nav-list {
            list-style: none;
            padding: 1.5rem 0.75rem;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 0.4rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            gap: 1rem;
        }

        .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--text-main);
        }

        .nav-link.active {
            background-color: var(--secondary);
            color: var(--primary);
        }

        .nav-icon {
            font-size: 22px;
        }

        /* Main Layout */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            width: calc(100% - 260px);
        }

        .content {
            padding: 2.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Universal Components */
        .dashboard-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        /* GLOBAL BUTTON SYSTEM */
        .btn, .btn-cancel, .btn-confirm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--primary);
            color: white;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-outline, .btn-cancel {
            background: transparent !important;
            border: 1px solid var(--border) !important;
            color: var(--text-main) !important;
            box-shadow: none !important;
        }

        .btn-confirm {
            background: var(--error) !important;
            color: white !important;
        }

        .btn:hover, .btn-cancel:hover, .btn-confirm:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn:active, .btn-cancel:active, .btn-confirm:active {
            transform: scale(0.98);
        }

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
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-m3-primary { background: var(--primary); color: white; }
        .btn-m3-outline { background: white; color: var(--text-main); border: 1px solid var(--border); }
        .btn-m3-tonal { background: var(--secondary); color: var(--primary); }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Modal Sub-system */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: var(--surface);
            border-radius: 20px;
            padding: 2rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .modal-title { font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; }
        .modal-text { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 2rem; line-height: 1.5; }
        
        .modal-actions, .lite-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
    </style>
</head>

<body>