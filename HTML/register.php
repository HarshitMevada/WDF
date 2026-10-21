<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
startStudentHubSession();

$errors = [];
$success = "";
$name = "";
$username = "";
$email = "";
$mobile = "";
$course = "";
$year = "";
$gender = "";

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = is_string($_POST["name"] ?? null) ? trim($_POST["name"]) : "";
    $username = is_string($_POST["username"] ?? null)
        ? strtolower(trim($_POST["username"]))
        : "";
    $email = is_string($_POST["email"] ?? null)
        ? strtolower(trim($_POST["email"]))
        : "";
    $mobile = is_string($_POST["mobile"] ?? null) ? trim($_POST["mobile"]) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $confirmPassword = is_string($_POST["confirmPassword"] ?? null) ? $_POST["confirmPassword"] : "";
    $course = is_string($_POST["course"] ?? null) ? trim($_POST["course"]) : "";
    $year = is_string($_POST["year"] ?? null) ? trim($_POST["year"]) : "";
    $gender = is_string($_POST["gender"] ?? null) ? trim($_POST["gender"]) : "";
    $terms = isset($_POST["terms"]);
    $submittedToken = is_string($_POST["csrf_token"] ?? null)
        ? $_POST["csrf_token"]
        : "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = "Your form session expired. Refresh the page and try again.";
    }

    if (!preg_match("/^[A-Za-z ]{3,120}$/", $name)) {
        $errors[] = "Enter a name with 3 to 120 letters and spaces.";
    }

    if (!preg_match("/^[a-zA-Z0-9_]{3,30}$/", $username)) {
        $errors[] = "Username must be 3 to 30 letters, numbers, or underscores.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        $errors[] = "Enter a valid 10 digit mobile number.";
    }

    if (!preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$!%*?&]).{8,}$/", $password)) {
        $errors[] = "Password needs 8 characters, uppercase, lowercase, a number, and a special character.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (!in_array($course, ["CSE", "IT", "CE", "Other"], true)) {
        $errors[] = "Please select a course.";
    }

    if (!in_array($year, ["1", "2", "3", "4"], true)) {
        $errors[] = "Please select your year.";
    }

    if (!in_array($gender, ["Male", "Female", "Other"], true)) {
        $errors[] = "Please select your gender.";
    }

    if (!$terms) {
        $errors[] = "Please accept the terms and conditions.";
    }

    if ($errors === []) {
        $mysqli = null;

        try {
            $mysqli = getDatabaseConnection();

            $duplicateStatement = $mysqli->prepare(
                "SELECT student_id FROM students
                 WHERE username = ? OR email = ? OR mobile = ?
                 LIMIT 1"
            );
            $duplicateStatement->bind_param("sss", $username, $email, $mobile);
            $duplicateStatement->execute();
            $duplicateStatement->store_result();
            $duplicateExists = $duplicateStatement->num_rows > 0;
            $duplicateStatement->close();

            if ($duplicateExists) {
                $errors[] = "That username, email, or mobile number is already registered.";
            } else {
                $mysqli->begin_transaction();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $studentStatement = $mysqli->prepare(
                    "INSERT INTO students (full_name, username, email, mobile, password_hash, gender)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $studentStatement->bind_param(
                    "ssssss",
                    $name,
                    $username,
                    $email,
                    $mobile,
                    $passwordHash,
                    $gender
                );
                $studentStatement->execute();
                $studentId = $mysqli->insert_id;
                $studentStatement->close();

                $registrationStatement = $mysqli->prepare(
                    "INSERT INTO registrations (student_id, course_code, study_year)
                     VALUES (?, ?, ?)"
                );
                $studyYear = (int) $year;
                $registrationStatement->bind_param("isi", $studentId, $course, $studyYear);
                $registrationStatement->execute();
                $registrationStatement->close();

                $auditStatement = $mysqli->prepare(
                    "INSERT INTO registration_audit (student_id, action)
                     VALUES (?, 'student_registered')"
                );
                $auditStatement->bind_param("i", $studentId);
                $auditStatement->execute();
                $auditStatement->close();

                $mysqli->commit();
                $success = "Registration successful!";
                session_regenerate_id(true);
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION["csrf_token"];
            }
        } catch (mysqli_sql_exception $exception) {
            if ($mysqli instanceof mysqli) {
                try {
                    $mysqli->rollback();
                } catch (mysqli_sql_exception $rollbackException) {
                    error_log("Student registration rollback failed: " . $rollbackException->getMessage());
                }
            }

            error_log("Student registration failed: " . $exception->getMessage());
            $errors[] = $exception->getCode() === 1062
                ? "That username, email, or mobile number is already registered."
                : "Unable to save registration data. Please try again later.";
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
    <title>Register Page</title>
    <link rel="stylesheet" href="style.css?v=20261021-1750">
</head>
<body class="body register-page">
    <nav class="auth-topbar">
        <a class="brand" href="home.html">CHARUSAT <span>STUDENT HUB</span></a>
        <a href="login.php">Sign in</a>
    </nav>
    <div class="maindiv">
        <div id="divregister1">
            <h1 style="text-align: center; background-color: #29524A;">Student Hub</h1>
            <ul class="list">
                <li>Lecture Attendance</li>
                <li>Lecture Time-table</li>
                <li>University Result</li>
                <li>E-Governance</li>
                <li>Fees Payment</li>
            </ul>
        </div>

        <div id="divregister2">
            <form class="form" id="registerForm" method="POST" novalidate>
                <h2>Student Registration</h2>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">

                <?php
                    foreach ($errors as $error) {
                        echo "<p style='color: darkred;'>" . htmlspecialchars($error) . "</p>";
                    }

                    if ($success != "") {
                        echo "<p style='color: darkgreen;'>" . htmlspecialchars($success) . "</p>";
                    }
                ?>
                
                <label for="name">Full Name:</label><br>
                <input type="text" id="name" name="name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>" maxlength="120" autocomplete="name">
                <span class="error" id="nameError"></span>
                <br>

                <label for="email">Email:</label><br>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>" maxlength="254" autocomplete="email">
                <span class="error" id="emailError"></span>
                <br>

                <label for="username">Username:</label><br>
                <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, "UTF-8"); ?>" minlength="3" maxlength="30" autocomplete="username">
                <span class="error" id="usernameError"></span>
                <br>

                <label for="mobile">Mobile Number:</label><br>
                <input type="tel" id="mobile" name="mobile" placeholder="Enter 10 digit mobile number" value="<?php echo htmlspecialchars($mobile, ENT_QUOTES, "UTF-8"); ?>" maxlength="10" autocomplete="tel">
                <span class="error" id="mobileError"></span>
                <br>

                <label for="password">Password:</label><br>
                <input type="password" id="password" name="password" placeholder="Enter password">
                <span class="error" id="passwordError"></span>
                <span id="strength"></span>
                <br>

                <label for="confirmPassword">Confirm Password:</label><br>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password">
                <span class="error" id="confirmPasswordError"></span>
                <br>

                <label for="course">Course:</label><br>
                <select id="course" name="course">
                    <option value="">Select Course</option>
                    <option value="CSE" <?php echo $course === "CSE" ? "selected" : ""; ?>>Computer Science</option>
                    <option value="IT" <?php echo $course === "IT" ? "selected" : ""; ?>>Information Technology</option>
                    <option value="CE" <?php echo $course === "CE" ? "selected" : ""; ?>>Computer Engineering</option>
                    <option value="Other" <?php echo $course === "Other" ? "selected" : ""; ?>>Other</option>
                </select>
                <span class="error" id="courseError"></span>
                <br>

                <label for="year">Year:</label><br>
                <select id="year" name="year">
                    <option value="">Select Year</option>
                    <option value="1" <?php echo $year === "1" ? "selected" : ""; ?>>First Year</option>
                    <option value="2" <?php echo $year === "2" ? "selected" : ""; ?>>Second Year</option>
                    <option value="3" <?php echo $year === "3" ? "selected" : ""; ?>>Third Year</option>
                    <option value="4" <?php echo $year === "4" ? "selected" : ""; ?>>Fourth Year</option>
                </select>
                <span class="error" id="yearError"></span>
                <br>

                <label>Gender:</label><br>

                <input type="radio" name="gender" id="male" value="Male" <?php echo $gender === "Male" ? "checked" : ""; ?>>
                <label for="male">Male</label>

                <input type="radio" name="gender" id="female" value="Female" <?php echo $gender === "Female" ? "checked" : ""; ?>>
                <label for="female">Female</label>

                <input type="radio" name="gender" id="other" value="Other" <?php echo $gender === "Other" ? "checked" : ""; ?>>
                <label for="other">Other</label>

                <br>
                <span class="error" id="genderError"></span>
                <br>

                <input type="checkbox" id="terms" name="terms" <?php echo isset($_POST["terms"]) ? "checked" : ""; ?>>
                <label for="terms">I accept the terms and conditions</label>
                <br>
                <span class="error" id="termsError"></span>
                <br>

                <button type="submit">Register</button>

                <p id="success"></p>
            </form>
    </div>
    <script src="script.js"></script>
</body>
</html>