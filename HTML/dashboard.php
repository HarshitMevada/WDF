<?php
    require_once __DIR__ . "/auth.php";

    $authenticatedStudent = requireStudentRole("student");
    $student = null;
    $databaseUnavailable = false;

    try {
        $mysqli = getDatabaseConnection();
        $statement = $mysqli->prepare(
            "SELECT students.full_name, students.email, students.mobile,
                    students.gender, registrations.course_code, registrations.study_year
             FROM registrations
             INNER JOIN students ON students.student_id = registrations.student_id
             WHERE students.student_id = ?
             ORDER BY registrations.registration_id DESC
             LIMIT 1"
        );
        $studentId = (int) $authenticatedStudent["student_id"];
        $statement->bind_param("i", $studentId);
        $statement->execute();
        $student = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();
    } catch (mysqli_sql_exception $exception) {
        error_log("Unable to load Student Hub dashboard: " . $exception->getMessage());
        $databaseUnavailable = true;
    } finally {
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $mysqli->close();
        }
    }

    function escapeOutput(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Student Hub</title>
    <link rel="stylesheet" href="style.css?v=20261022-1849">
</head>
<body class="body student-dashboard-page">
    <div class="navbar">
        <nav class="nav">
            <a href="home.html">CHARUSAT</a>
            <?php foreach (studentHubNavigationLinks($authenticatedStudent['role']) as $label => $href) { ?>
                <a href="<?php echo escapeOutput($href); ?>"><?php echo escapeOutput($label); ?></a>
            <?php } ?>
            <form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput(getStudentHubCsrfToken()); ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    </div>
    <main class="student-dashboard-content">
        <?php if ($databaseUnavailable) { ?>
            <section class="card dashboard-message">
                <h1>Dashboard unavailable</h1>
                <p>Student information is temporarily unavailable. Please try again later.</p>
            </section>
        <?php } elseif ($student != null) { ?>
            <header class="student-dashboard-welcome">
                <div>
                    <p class="eyebrow">YOUR STUDENT HUB</p>
                    <h1>Welcome back, <?php echo escapeOutput($student["full_name"]); ?></h1>
                    <p>Here’s a quick overview of your academic services.</p>
                </div>
                <div class="student-dashboard-course">
                    <span>ENROLLED COURSE</span>
                    <strong><?php echo escapeOutput($student["course_code"]); ?></strong>
                    <small>Year <?php echo escapeOutput((string) $student["study_year"]); ?></small>
                </div>
            </header>
            <section class="student-dashboard-services" aria-label="Academic services">
                <article class="student-service-card">
                    <div class="student-service-heading">
                        <span class="student-service-icon" aria-hidden="true">▦</span>
                        <div><p class="section-kicker">ACADEMICS</p><h2>Time table</h2></div>
                    </div>
                    <div class="student-service-content">
                        <p class="student-service-status">Schedule not published</p>
                        <p>Your class timetable will appear here when it is available in Student Hub.</p>
                    </div>
                    <a href="course_registration.html">Review course options <span aria-hidden="true">→</span></a>
                </article>

                <article class="student-service-card">
                    <div class="student-service-heading">
                        <span class="student-service-icon" aria-hidden="true">◔</span>
                        <div><p class="section-kicker">ATTENDANCE</p><h2>Lecture attendance</h2></div>
                    </div>
                    <div class="student-service-content">
                        <p class="student-service-status">No attendance data</p>
                        <p>Attendance records are not connected to Student Hub yet.</p>
                    </div>
                    <a href="feedback.html">Share feedback <span aria-hidden="true">→</span></a>
                </article>

                <article class="student-service-card">
                    <div class="student-service-heading">
                        <span class="student-service-icon" aria-hidden="true">₹</span>
                        <div><p class="section-kicker">STUDENT SERVICES</p><h2>Fees details</h2></div>
                    </div>
                    <div class="student-service-content">
                        <p class="student-service-status">Payment demo</p>
                        <p>View the fees page. It is a demonstration and does not process payments.</p>
                    </div>
                    <a href="fees_payment.html">Open fees page <span aria-hidden="true">→</span></a>
                </article>

                <article class="student-service-card">
                    <div class="student-service-heading">
                        <span class="student-service-icon" aria-hidden="true">✦</span>
                        <div><p class="section-kicker">ACADEMICS</p><h2>University result</h2></div>
                    </div>
                    <div class="student-service-content">
                        <p class="student-service-status">No results published</p>
                        <p>Your results will appear here when result data is available in Student Hub.</p>
                    </div>
                    <a href="exam_schedule.html">View exam schedule <span aria-hidden="true">→</span></a>
                </article>
            </section>

            <section class="student-dashboard-profile" aria-label="Student profile summary">
                <span>Signed in as</span>
                <strong><?php echo escapeOutput($student["full_name"]); ?></strong>
                <span><?php echo escapeOutput($student["email"]); ?></span>
                <a href="profile.html">View profile <span aria-hidden="true">→</span></a>
            </section>
        <?php } else { ?>
            <section class="card dashboard-message">
                <h1>No student data</h1>
                <p>Please register a student first.</p>
            </section>
        <?php } ?>
    </main>
    <script src="script.js"></script>
</body>
</html> 