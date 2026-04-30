<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgoMeal | Core Access</title>
    <link rel="icon" type="image/svg+xml" href="assets/Algomeal.svg">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0061FF;
            --accent: #60A5FA;
            --bg: #FFFFFF;
            --text: #111827;
            --text-muted: #6B7280;
            --border: rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-image: 
                radial-gradient(at 100% 0%, rgba(0, 97, 255, 0.05) 0px, transparent 40%),
                radial-gradient(at 0% 100%, rgba(96, 165, 250, 0.05) 0px, transparent 40%);
        }

        .ambient-glow {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 86c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm66 3c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm-46-43c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm0-34c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm54 54c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM80 7c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM39 18c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm41 22c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM44 57c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm-33 2c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm70 31c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM41 70c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM33 46c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm59 31c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM58 3c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zM6 14c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm22 0c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm54 64c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1z' fill='%230061ff' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.5;
            z-index: 0;
        }

        .main-entrance {
            position: relative;
            z-index: 10;
            text-align: center;
            max-width: 540px;
            width: 100%;
            padding: 2rem;
            animation: slideIn 1s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-ring {
            width: 140px;
            height: 140px;
            margin: 0 auto 3rem;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-ring::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 1px solid var(--border);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.02);
            animation: ripple 4s infinite linear;
        }

        @keyframes ripple {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.2; }
            100% { transform: scale(1); opacity: 0.5; }
        }

        .system-logo {
            width: 80px;
            height: 80px;
            background: white;
            padding: 15px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            z-index: 2;
        }

        .system-logo img {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 4px 8px rgba(0, 97, 255, 0.2));
        }

        .brand-text h1 {
            font-size: 3.5rem;
            font-weight: 900;
            letter-spacing: -2px;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .brand-text h1 span {
            color: var(--primary);
        }

        .brand-text p {
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 500;
            max-width: 380px;
            margin: 0 auto 3.5rem;
            line-height: 1.6;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 1.25rem 2.5rem;
            border-radius: 100px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 40px -10px rgba(0, 97, 255, 0.4);
        }

        .action-button:hover {
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 25px 50px -12px rgba(0, 97, 255, 0.5);
            background: #0052D9;
        }

        .action-button:active {
            transform: scale(0.98);
        }

        .system-footer {
            position: fixed;
            bottom: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 4rem;
            opacity: 0.6;
        }

        .metric-unit {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            text-align: center;
        }

        .metric-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
        }

        .metric-value {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text);
        }

        .scanline {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(to right, transparent, rgba(0, 97, 255, 0.05), transparent);
            animation: scan 8s infinite linear;
            z-index: 1;
        }

        @keyframes scan {
            from { top: -100px; }
            to { top: 100vh; }
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="scanline"></div>

    <main class="main-entrance">
        <div class="logo-ring">
            <div class="system-logo">
                <img src="assets/Algomeal.svg" alt="AlgoMeal">
            </div>
        </div>

        <div class="brand-text">
            <h1>ALGO<span>MEAL</span></h1>
            <p>School-Based Feeding Program & Nutritional Management System.</p>
        </div>

        <a href="login.php" class="action-button">
            Enter Portal
            <span class="material-icons">login</span>
        </a>
    </main>

    <footer class="system-footer">
        <div class="metric-unit">
            <div class="metric-label">Nutrition Engine</div>
            <div class="metric-value">Active & Optimized</div>
        </div>
        <div class="metric-unit">
            <div class="metric-label">Budget Tracking</div>
            <div class="metric-value">Institutional Standard</div>
        </div>
        <div class="metric-unit">
            <div class="metric-label">System Status</div>
            <div class="metric-value">Secure Connection</div>
        </div>
    </footer>
</body>
</html>