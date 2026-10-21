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
    <title>Dashboard Page</title>
    <link rel="stylesheet" href="style.css?v=20261021-1815">
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
    <br><br>
    <div class="dashboard">
            <?php if ($databaseUnavailable) { ?>
                <div class="card">
                    <h2>Dashboard Unavailable</h2>
                    <p>Student information is temporarily unavailable. Please try again later.</p>
                </div>
            <?php } elseif ($student != null) { ?>
                <div class="card student-card">
                    <h2>Student Profile</h2>
                    <p><b>Name:</b> <?php echo escapeOutput($student["full_name"]); ?></p>
                    <p><b>Email:</b> <?php echo escapeOutput($student["email"]); ?></p>
                    <p><b>Mobile:</b> <?php echo escapeOutput($student["mobile"]); ?></p>
                    <p><b>Course:</b> <?php echo escapeOutput($student["course_code"]); ?></p>
                    <p><b>Year:</b> <?php echo escapeOutput((string) $student["study_year"]); ?></p>
                    <p><b>Gender:</b> <?php echo escapeOutput($student["gender"]); ?></p>
                </div>

                <div class="card">
                    <h2>Academic Information</h2>
                    <p><b>Course:</b> <?php echo escapeOutput($student["course_code"]); ?></p>
                    <p><b>Current Year:</b> <?php echo escapeOutput((string) $student["study_year"]); ?></p>
                    <p><b>Status:</b> Active Student</p>
                </div>

                <div class="card">
                    <h2>Student Services</h2>
                    <p>Lecture Attendance</p>
                    <p>Lecture Time-table</p>
                    <p>University Result</p>
                    <p>Fees Payment</p>
                    <p>E-Governance</p>
                </div>

            <?php } else { ?>
                <div class="card">
                    <h2>No Student Data</h2>
                    <p>Please register a student first.</p>
                </div>
            <?php } ?>
        </div>
    <script src="script.js"></script>
</body>
</html> 