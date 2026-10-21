<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$authenticatedStudent = getAuthenticatedStudent();
startStudentHubSession();
if ($authenticatedStudent !== null) {
    studentHubRedirectForRole($authenticatedStudent['role']);
}

if (!isset($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = is_string($_POST['identity'] ?? null) ? trim($_POST['identity']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if (!hash_equals($_SESSION['login_csrf_token'], $submittedToken)) {
        http_response_code(403);
        $error = 'Your login session expired. Refresh the page and try again.';
    } elseif ($identity === '' || $password === '') {
        $error = 'Enter your username/email and password.';
    } else {
        $mysqli = null;
        try {
            $mysqli = getDatabaseConnection();
            $statement = $mysqli->prepare(
                'SELECT student_id, full_name, username, password_hash, role
                 FROM students
                 WHERE (username = ? OR email = ?) AND deleted_at IS NULL
                 LIMIT 1'
            );
            $statement->bind_param('ss', $identity, $identity);
            $statement->execute();
            $account = $statement->get_result()->fetch_assoc() ?: null;
            $statement->close();

            if ($account === null || !password_verify($password, $account['password_hash'])) {
                $error = 'Invalid username/email or password.';
            } else {
                $studentId = (int) $account['student_id'];
                $updateStatement = $mysqli->prepare(
                    'UPDATE students SET last_login_at = CURRENT_TIMESTAMP WHERE student_id = ?'
                );
                $updateStatement->bind_param('i', $studentId);
                $updateStatement->execute();
                $updateStatement->close();
                if ($account['role'] === 'admin') {
                    recordAdminAudit($mysqli, $studentId, 'admin_login', 'session');
                }

                session_regenerate_id(true);
                $_SESSION['student_id'] = $studentId;
                $_SESSION['role'] = $account['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

                if (isset($_POST['remember_me'])) {
                    rememberStudent($mysqli, $studentId);
                } else {
                    $deleteToken = $mysqli->prepare(
                        'DELETE FROM remember_tokens WHERE student_id = ?'
                    );
                    $deleteToken->bind_param('i', $studentId);
                    $deleteToken->execute();
                    $deleteToken->close();
                    clearRememberCookie();
                }

                $mysqli->close();
                studentHubRedirectForRole($account['role']);
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Student Hub login failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Login is temporarily unavailable. Please try again later.';
        } finally {
            if ($mysqli instanceof mysqli) {
                $mysqli->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Student Hub</title>
    <link rel="stylesheet" href="style.css?v=20261021-1750">
</head>
<body class="body login-page">
    <nav class="auth-topbar">
        <a class="brand" href="home.html">CHARUSAT <span>STUDENT HUB</span></a>
        <a href="register.php">Create account</a>
    </nav>
    <div class="maindiv">
        <div id="divlogin1">
            <h1 style="text-align: center;">Student Hub</h1>
            <ul class="list">
                <li>Lecture Attendance</li>
                <li>Lecture Time-table</li>
                <li>University Result</li>
                <li>E-Governance</li>
                <li>Fees Payment</li>
            </ul>
        </div>
        <div id="divlogin2" class="div">
            <form class="form" method="POST" action="login.php">
                <h1 style="text-align: center; font-size: x-large;">Charusat Student Portal</h1>
                <?php if ($error !== '') { ?>
                    <p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php } ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['login_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <label for="identity">Username or email:</label><br>
                <input type="text" id="identity" name="identity" placeholder="Username or email" value="<?php echo htmlspecialchars($identity, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required>
                <br><br>

                <label for="password">Password:</label><br>
                <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                <br><br>

                <label>
                    <input type="checkbox" name="remember_me" value="1">
                    Remember me for up to 30 days
                </label>
                <br><br>

                <button type="submit">Login</button>
                <p><a href="register.php">Register Account</a></p>
            </form>
        </div>
    </div>
</body>
</html>
