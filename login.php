<?php
session_start();
require_once 'db.php';

$error = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $faculty_name = trim($_POST['faculty_name']);
    $password = $_POST['password'];

    if (empty($faculty_name) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, password_hash, role, status FROM users WHERE faculty_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $faculty_name);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $password_hash, $role, $status);
                $stmt->fetch();

                if ($status === 'Disabled') {
                    $error = "This account has been disabled by an administrator.";
                } else if (password_verify($password, $password_hash)) {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['faculty_name'] = $faculty_name;
                    $_SESSION['role'] = $role;
                    header("Location: pages/dashboard/dashboard.php");
                    exit;
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Account not found.";
            }
            $stmt->close();
        } else {
            $error = "Database error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgoMeal | Secure Access</title>
    <link rel="icon" type="image/svg+xml" href="assets/Algomeal.svg">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0061FF;
            --bg: #F6F8FA;
            --text: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
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
            background-image: radial-gradient(at 100% 0%, rgba(0, 97, 255, 0.05) 0px, transparent 50%);
        }

        .login-card {
            background: white;
            width: 100%;
            max-width: 420px;
            padding: 3rem;
            border-radius: 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.05);
            border: 1px solid white;
        }

        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            margin-bottom: 1rem;
        }

        .brand h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .brand p { color: var(--text-muted); font-size: 0.9rem; font-weight: 500; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.6rem;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            outline: none;
            transition: all 0.2s;
            background: #F9FAFB;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 97, 255, 0.1);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
            box-shadow: 0 10px 20px -5px rgba(0, 97, 255, 0.3);
        }

        .btn:hover {
            background: #0052D9;
            transform: translateY(-1px);
            box-shadow: 0 15px 30px -8px rgba(0, 97, 255, 0.4);
        }

        .alert {
            padding: 1rem;
            background: #FEE2E2;
            color: #991B1B;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <img src="assets/Algomeal.svg" alt="Logo" class="brand-logo">
            <h1>Portal Access</h1>
            <p>Authorized Personnel Only</p>
        </div>

        <?php if ($error): ?>
            <div class="alert">
                <span class="material-icons" style="font-size: 18px;">error_outline</span>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="faculty_name" class="form-control" required placeholder="Enter username" value="<?= htmlspecialchars($_POST['faculty_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn">Sign In to Dashboard</button>
        </form>
    </div>
</body>
</html>