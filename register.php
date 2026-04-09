<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $faculty_name = trim($_POST['faculty_name']);
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($faculty_name) || empty($password) || empty($role) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['Admin', 'Faculty'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE faculty_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $faculty_name);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Faculty or Admin name already exists.";
            } else {
                $stmt->close();

                // Proceed with insertion
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare("INSERT INTO users (faculty_name, password_hash, role) VALUES (?, ?, ?)");

                if ($insert_stmt) {
                    $insert_stmt->bind_param("sss", $faculty_name, $hashed_password, $role);
                    if ($insert_stmt->execute()) {
                        $success = "Registration successful! You can now access the portal.";
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                    $insert_stmt->close();
                } else {
                    $error = "Database error constraints.";
                }
            }
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
    <title>Register | AlgoMeal</title>
    <!-- Modern Font: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f5f5f5;
            --card-bg: #ffffff;
            --primary: #1a73e8;
            /* Google Material Blue */
            --primary-hover: #1557b0;
            --text-main: #202124;
            --text-muted: #5f6368;
            --border: #dadce0;
            --input-bg: #ffffff;
            --error-color: #d93025;
            --error-bg: #fce8e6;
            --success-color: #1e8e3e;
            --success-bg: #e6f4ea;
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
            max-width: 440px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 3rem 2.5rem;
            /* Material Design Elevation 3 */
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16), 0 3px 6px rgba(0, 0, 0, 0.23);
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

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/200%2Fsvg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235f6368' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        select.form-control option {
            background-color: var(--card-bg);
            color: var(--text-main);
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
            box-shadow: 0 3px 1px -2px rgba(0, 0, 0, 0.2), 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 1px 5px 0 rgba(0, 0, 0, 0.12);
        }

        .btn:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.2), 0 4px 5px 0 rgba(0, 0, 0, 0.14), 0 1px 10px 0 rgba(0, 0, 0, 0.12);
        }

        .btn:active {
            box-shadow: 0 5px 5px -3px rgba(0, 0, 0, 0.2), 0 8px 10px 1px rgba(0, 0, 0, 0.14), 0 3px 14px 2px rgba(0, 0, 0, 0.12);
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

        .alert-success {
            background: var(--success-bg);
            color: var(--success-color);
            border: 1px solid rgba(30, 142, 62, 0.2);
        }

        .login-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card">
            <div class="brand">
                <img src="assets/Algomeal.svg" alt="AlgoMeal Logo" class="brand-logo" style="background: transparent; box-shadow: none; border-radius: 0;">
                <h1>Create Account</h1>
                <p>Register as Faculty or Administrator</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="faculty_name">Username</label>
                    <input type="text" id="faculty_name" name="faculty_name" class="form-control"
                        placeholder="e.g., Juan Dela Cruz" required
                        value="<?php echo isset($_POST['faculty_name']) ? htmlspecialchars($_POST['faculty_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="role">Role Designation</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="" disabled <?php echo (!isset($_POST['role'])) ? 'selected' : ''; ?>>Select a
                            role</option>
                        <option value="Faculty" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Faculty') ? 'selected' : ''; ?>>Faculty / Teacher</option>
                        <option value="Admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Admin') ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Create a secure password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                        placeholder="Confirm your password" required minlength="6">
                </div>

                <button type="submit" class="btn">Create Account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <!-- Micro-animation script to add subtle interaction -->
    <script>
        document.querySelectorAll('.form-control').forEach(element => {
            element.addEventListener('focus', function () {
                this.parentElement.style.transform = 'translateX(5px)';
                this.parentElement.style.transition = 'transform 0.3s ease';
            });

            element.addEventListener('blur', function () {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>

</html>