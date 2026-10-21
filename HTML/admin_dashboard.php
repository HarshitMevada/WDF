<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = requireStudentRole('admin');
$databaseUnavailable = false;
$studentCount = 0;
$registrationCount = 0;
$recentStudents = [];
$mysqli = null;

try {
    $mysqli = getDatabaseConnection();
    $studentCount = (int) $mysqli->query(
        "SELECT COUNT(*) AS total FROM students WHERE role = 'student' AND deleted_at IS NULL"
    )->fetch_assoc()['total'];
    $registrationCount = (int) $mysqli->query(
        'SELECT COUNT(*) AS total FROM registrations'
    )->fetch_assoc()['total'];
    $recentStudents = $mysqli->query(
        "SELECT username, full_name, email, last_login_at
         FROM students
         WHERE role = 'student' AND deleted_at IS NULL
         ORDER BY student_id DESC
         LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
    error_log('Unable to load Student Hub admin dashboard: ' . $exception->getMessage());
    $databaseUnavailable = true;
} finally {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

function escapeAdminOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Student Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body">
    <div class="navbar">
        <nav class="nav">
            <a href="home.html">CHARUSAT</a>
            <a href="admin_dashboard.php">Admin Dashboard</a>
            <a href="admin_students.php">Manage Students</a>
            <a href="admin_events.php">Manage Events</a>
            <form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeAdminOutput(getStudentHubCsrfToken()); ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    </div>
    <main class="dashboard">
        <div class="card">
            <h1>Administrator Dashboard</h1>
            <p>Welcome, <?php echo escapeAdminOutput($admin['full_name']); ?>.</p>
        </div>
        <?php if ($databaseUnavailable) { ?>
            <div class="card">
                <h2>Dashboard Unavailable</h2>
                <p>Administrative data is temporarily unavailable. Please try again later.</p>
            </div>
        <?php } else { ?>
            <div class="card">
                <h2>Student Accounts</h2>
                <p><?php echo $studentCount; ?> students</p>
                <p><?php echo $registrationCount; ?> course registrations</p>
            </div>
            <div class="card">
                <h2>Recently Registered Students</h2>
                <?php if ($recentStudents === []) { ?>
                    <p>No student accounts have been registered.</p>
                <?php } else { ?>
                    <?php foreach ($recentStudents as $recentStudent) { ?>
                        <p>
                            <b><?php echo escapeAdminOutput($recentStudent['full_name']); ?></b>
                            (<?php echo escapeAdminOutput($recentStudent['username']); ?>) —
                            <?php echo escapeAdminOutput($recentStudent['email']); ?>;
                            last login:
                            <?php echo escapeAdminOutput($recentStudent['last_login_at'] ?? 'Never'); ?>
                        </p>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
    <script src="script.js"></script>
</body>
</html>
