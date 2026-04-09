<?php
session_start();
require_once 'db.php';

$error = '';

// If already logged in, redirect to dashboard (to be created later)
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
        $stmt = $conn->prepare("SELECT user_id, password_hash, role FROM users WHERE faculty_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $faculty_name);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $password_hash, $role);
                $stmt->fetch();
                
                if (password_verify($password, $password_hash)) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['faculty_name'] = $faculty_name;
                    $_SESSION['role'] = $role;
                    
                    // Redirect to a dashboard based on role or home page
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
            $error = "Database error: unable to prepare statement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AlgoMeal</title>
    <!-- Material Font: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f5f5f5;
            --card-bg: #ffffff;
            --primary: #1a73e8; /* Google Material Blue */
            --primary-hover: #1557b0;
            --text-main: #202124;
            --text-muted: #5f6368;
            --border: #dadce0;
            --input-bg: #ffffff;
            --error-color: #d93025;
            --error-bg: #fce8e6;
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            width: 100%;
            max-width: 400px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 3rem 2.5rem;
            /* Material Design Elevation 3 */
            box-shadow: 0 3px 6px rgba(0,0,0,0.16), 0 3px 6px rgba(0,0,0,0.23);
        }

        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
            user-select: none;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            background: var(--primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: white;
            font-size: 28px;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .brand h1 {
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .brand p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.875rem 1rem;
            color: var(--text-main);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            /* Material Focus states */
            border-width: 2px;
            padding: calc(0.875rem - 1px) calc(1rem - 1px);
        }

        .form-control::placeholder {
            color: #80868b;
        }

        .btn {
            width: 100%;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            margin-top: 1rem;
            box-shadow: 0 3px 1px -2px rgba(0,0,0,0.2), 0 2px 2px 0 rgba(0,0,0,0.14), 0 1px 5px 0 rgba(0,0,0,0.12);
        }

        .btn:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 2px 4px -1px rgba(0,0,0,0.2), 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12);
        }
        
        .btn:active {
            box-shadow: 0 5px 5px -3px rgba(0,0,0,0.2), 0 8px 10px 1px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12);
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-color);
            border: 1px solid rgba(217, 48, 37, 0.2);
        }

        .register-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <div class="brand">
                <img src="assets/Algomeal.svg" alt="AlgoMeal Logo" class="brand-logo" style="background: transparent; box-shadow: none; border-radius: 0;">
                <h1>Sign In</h1>
                <p>Welcome back to <strong>Algo</strong>Meal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="faculty_name">Username</label>
                    <input type="text" id="faculty_name" name="faculty_name" class="form-control" placeholder="Enter your username" required value="<?php echo isset($_POST['faculty_name']) ? htmlspecialchars($_POST['faculty_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn">Sign In</button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register</a>
            </div>
        </div>
    </div>

    <script>
        // Micro-animations for form focus
        document.querySelectorAll('.form-control').forEach(element => {
            element.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(2px)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });
            
            element.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>
