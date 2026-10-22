<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = requireStudentRole('admin');
$databaseUnavailable = false;
$studentCount = 0;
$adminCount = 0;
$registrationCount = 0;
$eventCount = 0;
$recentRegistrations = [];
$recentStudentActivity = [];
$recentAdminActions = [];
$mysqli = null;

try {
    $mysqli = getDatabaseConnection();
    $studentCount = (int) $mysqli->query(
        "SELECT COUNT(*) AS total
         FROM students
         WHERE role = 'student' AND deleted_at IS NULL"
    )->fetch_assoc()['total'];
    $adminCount = (int) $mysqli->query(
        "SELECT COUNT(*) AS total
         FROM students
         WHERE role = 'admin' AND deleted_at IS NULL"
    )->fetch_assoc()['total'];
    $registrationCount = (int) $mysqli->query(
        'SELECT COUNT(*) AS total FROM registrations'
    )->fetch_assoc()['total'];
    $eventCount = (int) $mysqli->query(
        'SELECT COUNT(*) AS total FROM events'
    )->fetch_assoc()['total'];
    $recentRegistrations = $mysqli->query(
        'SELECT s.full_name, s.username, c.course_name, r.study_year, r.registered_at
         FROM registrations AS r
         INNER JOIN students AS s ON s.student_id = r.student_id
         INNER JOIN courses AS c ON c.course_code = r.course_code
         ORDER BY r.registered_at DESC, r.registration_id DESC
         LIMIT 10'
    )->fetch_all(MYSQLI_ASSOC);
    $recentStudentActivity = $mysqli->query(
        "SELECT activity.full_name, activity.username, activity.action, activity.activity_at
         FROM (
             SELECT s.full_name, s.username, ra.action, ra.created_at AS activity_at,
                    ra.audit_id AS activity_id
             FROM registration_audit AS ra
             INNER JOIN students AS s ON s.student_id = ra.student_id
             WHERE s.role = 'student' AND ra.action = 'student_registered'
             UNION ALL
             SELECT s.full_name, s.username, 'student_login' AS action,
                    s.last_login_at AS activity_at, s.student_id AS activity_id
             FROM students AS s
             WHERE s.role = 'student' AND s.last_login_at IS NOT NULL
         ) AS activity
         ORDER BY activity.activity_at DESC, activity.activity_id DESC
         LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);
    $recentAdminActions = $mysqli->query(
        'SELECT a.action, a.entity_type, a.entity_id, a.created_at,
                COALESCE(s.full_name, "Removed administrator") AS admin_name
         FROM audit_logs AS a
         LEFT JOIN students AS s ON s.student_id = a.admin_student_id
         ORDER BY a.created_at DESC, a.audit_id DESC
         LIMIT 10'
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
    <link rel="stylesheet" href="style.css?v=20261022-1827">
</head>
<body class="body admin-page">
    <div class="navbar">
        <nav class="nav admin-dashboard-nav">
            <a href="home.html">CHARUSAT</a>
            <?php foreach (studentHubNavigationLinks($admin['role']) as $label => $href) { ?>
                <a href="<?php echo escapeAdminOutput($href); ?>"><?php echo escapeAdminOutput($label); ?></a>
            <?php } ?>
            <form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeAdminOutput(getStudentHubCsrfToken()); ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    </div>
    <main class="admin-dashboard">
        <section class="card admin-dashboard-welcome">
            <h1>Administrator Dashboard</h1>
            <p>Welcome, <?php echo escapeAdminOutput($admin['full_name']); ?>.</p>
            <p>Live overview of Student Hub accounts, registrations, events, and admin activity.</p>
        </section>
        <?php if ($databaseUnavailable) { ?>
            <section class="card">
                <h2>Dashboard Unavailable</h2>
                <p>Administrative data is temporarily unavailable. Please try again later.</p>
            </section>
        <?php } else { ?>
            <section class="admin-stat-grid" aria-label="Student Hub statistics">
                <article class="card admin-stat">
                    <h2>Active Students</h2>
                    <p><?php echo $studentCount; ?></p>
                </article>
                <article class="card admin-stat">
                    <h2>Administrator Accounts</h2>
                    <p><?php echo $adminCount; ?></p>
                </article>
                <article class="card admin-stat">
                    <h2>Events</h2>
                    <p><?php echo $eventCount; ?></p>
                </article>
                <article class="card admin-stat">
                    <h2>Course Registrations</h2>
                    <p><?php echo $registrationCount; ?></p>
                </article>
            </section>
            <section class="card admin-dashboard-section">
                <h2>Latest Course Registrations</h2>
                <?php if ($recentRegistrations === []) { ?>
                    <p>No course registrations have been recorded.</p>
                <?php } else { ?>
                    <div class="admin-dashboard-table-wrap">
                        <table class="admin-dashboard-table">
                            <thead>
                                <tr><th>Student</th><th>Username</th><th>Course</th><th>Year</th><th>Registered</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRegistrations as $registration) { ?>
                                    <tr>
                                        <td><?php echo escapeAdminOutput($registration['full_name']); ?></td>
                                        <td><?php echo escapeAdminOutput($registration['username']); ?></td>
                                        <td><?php echo escapeAdminOutput($registration['course_name']); ?></td>
                                        <td><?php echo (int) $registration['study_year']; ?></td>
                                        <td><?php echo escapeAdminOutput($registration['registered_at']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>
            <section class="card admin-dashboard-section">
                <h2>Recent Student Activity</h2>
                <?php if ($recentStudentActivity === []) { ?>
                    <p>No student activity has been recorded yet.</p>
                <?php } else { ?>
                    <div class="admin-dashboard-table-wrap">
                        <table class="admin-dashboard-table">
                            <thead>
                                <tr><th>Student</th><th>Username</th><th>Activity</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudentActivity as $activity) { ?>
                                    <tr>
                                        <td><?php echo escapeAdminOutput($activity['full_name']); ?></td>
                                        <td><?php echo escapeAdminOutput($activity['username']); ?></td>
                                        <td><?php echo escapeAdminOutput(ucwords(str_replace('_', ' ', $activity['action']))); ?></td>
                                        <td><?php echo escapeAdminOutput($activity['activity_at']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>
            <section class="card admin-dashboard-section">
                <h2>Recent Admin Activity</h2>
                <?php if ($recentAdminActions === []) { ?>
                    <p>No administrator actions have been recorded yet.</p>
                <?php } else { ?>
                    <div class="admin-dashboard-table-wrap">
                        <table class="admin-dashboard-table">
                            <thead>
                                <tr><th>Administrator</th><th>Action</th><th>Item</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAdminActions as $audit) { ?>
                                    <tr>
                                        <td><?php echo escapeAdminOutput($audit['admin_name']); ?></td>
                                        <td><?php echo escapeAdminOutput(ucwords(str_replace('_', ' ', $audit['action']))); ?></td>
                                        <td>
                                            <?php echo escapeAdminOutput(ucfirst($audit['entity_type'])); ?>
                                            <?php echo $audit['entity_id'] === null ? '' : '#' . (int) $audit['entity_id']; ?>
                                        </td>
                                        <td><?php echo escapeAdminOutput($audit['created_at']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>
    </main>
    <script src="script.js"></script>
</body>
</html>
