<?php
session_start();
// If user is already logged in, redirect them to the dashboard
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
    <title>AlgoMeal - Nutritional Management Ecosystem</title>
    <link rel="icon" type="image/svg+xml" href="assets/Algomeal.svg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0061ff;
            --primary-glow: rgba(0, 97, 255, 0.5);
            --bg: #f8f9fc;
            --text-main: #1a1f36;
            --text-muted: #697386;
            --surface: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Ambient Background Accents */
        .ambient-blob {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0, 97, 255, 0.05) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(40px);
        }

        .blob-1 { top: -200px; left: -200px; }
        .blob-2 { bottom: -200px; right: -200px; }

        .container {
            text-align: center;
            z-index: 10;
            animation: fadeIn 1.2s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-wrapper {
            margin-bottom: 2.5rem;
            position: relative;
            display: inline-block;
        }

        .main-logo {
            width: 140px;
            height: 140px;
            animation: float 6s ease-in-out infinite;
            filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.05));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .branding {
            margin-bottom: 3rem;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        h1 span {
            color: var(--primary);
        }

        p {
            font-size: 1.125rem;
            color: var(--text-muted);
            font-weight: 500;
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .cta-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .btn-get-started {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--primary);
            color: #ffffff;
            text-decoration: none;
            padding: 1.1rem 2.5rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 10px 25px rgba(0, 97, 255, 0.3);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2px solid transparent;
        }

        .btn-get-started:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 97, 255, 0.4);
            letter-spacing: 0.5px;
        }

        .btn-get-started:active {
            transform: scale(0.98);
        }

        .footer-text {
            position: absolute;
            bottom: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            width: 100%;
            text-align: center;
        }

        /* Subtle scroll reveal for the logo */
        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            background: var(--primary-glow);
            filter: blur(60px);
            opacity: 0.15;
            z-index: -1;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="ambient-blob blob-1"></div>
    <div class="ambient-blob blob-2"></div>

    <div class="container">
        <div class="logo-wrapper">
            <div class="logo-glow"></div>
            <img src="assets/Algomeal.svg" alt="AlgoMeal Logo" class="main-logo">
        </div>

        <div class="branding">
            <h1>Algo<span>Meal</span></h1>
            <p>Advanced Nutritional Management for the Modern School Ecosystem</p>
        </div>

        <div class="cta-group">
            <a href="login.php" class="btn-get-started">
                Get Started
                <span class="material-icons">east</span>
            </a>
        </div>
    </div>

    <footer class="footer-text">
        &copy; <?php echo date('Y'); ?> AlgoMeal &middot; Empowering Nutritional Wellness
    </footer>
</body>
</html>
