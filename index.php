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
    <title>AlgoMeal - Advanced Nutritional Ecosystem</title>
    <link rel="icon" type="image/svg+xml" href="assets/Algomeal.svg">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --bg-base: #06080F;
            --surface: #0B0E17;
            --surface-hover: #151A2A;
            --primary: #3B82F6;
            --primary-glow: rgba(59, 130, 246, 0.6);
            --accent: #8B5CF6;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --border: #1E293B;
            --glass: rgba(15, 23, 42, 0.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
        }

        /* Navbar */
        nav {
            padding: 1.5rem 4rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .brand img {
            width: 32px;
            height: 32px;
            filter: drop-shadow(0 0 10px var(--primary-glow));
        }

        .btn-ghost {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Hero Section */
        .hero {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 8rem 2rem 4rem 2rem;
            position: relative;
            z-index: 10;
        }

        .badge-new {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #60A5FA;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(4px);
        }

        h1 {
            font-size: 5rem;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -2px;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #ffffff, #94A3B8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            max-width: 900px;
        }

        h1 span {
            background: linear-gradient(to right, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            font-size: 1.25rem;
            color: var(--text-muted);
            max-width: 600px;
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        .cta-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #2563EB);
            color: white;
            text-decoration: none;
            padding: 1rem 2.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.125rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 10px 30px -10px var(--primary-glow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px -10px var(--primary-glow);
        }

        .btn-primary:hover::after {
            left: 100%;
        }

        /* Features Grid */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            width: 100%;
            padding: 0 2rem;
            margin: 0 auto 5rem;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, var(--primary), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: var(--surface-hover);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Abstract Grid Pattern Background */
        .bg-grid {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(circle at center, black, transparent 80%);
            -webkit-mask-image: radial-gradient(circle at center, black, transparent 80%);
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>

    <nav>
        <div class="brand">
            <img src="assets/Algomeal.svg" alt="AlgoMeal Logo">
            AlgoMeal
        </div>
        <div>
            <a href="login.php" class="btn-ghost">Faculty Login</a>
        </div>
    </nav>

    <main class="hero">
        <div class="badge-new">
            <span class="material-icons" style="font-size: 16px;">celebration</span>
            v2.0 Constraint Engine Active
        </div>
        
        <h1>Intelligent <span>Nutritional</span> Routing for Schools.</h1>
        <p class="subtitle">AlgoMeal automatically resolves dietary restrictions, balances macronutrients, and adheres to strict fiscal constraints using advanced Constraint Simulation processing.</p>
        
        <div class="cta-container">
            <a href="login.php" class="btn-primary">
                Enter Command Center
                <span class="material-icons">arrow_forward</span>
            </a>
        </div>

        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-icons">architecture</span>
                </div>
                <h3>Constraint Execution</h3>
                <p>Simultaneously balances over 20+ dietary tags ranging from Vegan to strict allergen exclusions in milliseconds.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-icons">trending_up</span>
                </div>
                <h3>BMI Forecasting</h3>
                <p>Monitors height-to-weight ratios to automatically flag malnutrition and prescribe baseline healthy target weights.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-icons">monitor_heart</span>
                </div>
                <h3>Fiscal Telemetry</h3>
                <p>Tracks actual spending against maximum defined tolerances per serving to ensure absolute financial stability.</p>
            </div>
        </div>
    </main>
</body>
</html>
