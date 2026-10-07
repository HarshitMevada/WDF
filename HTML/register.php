<?php
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $terms = isset($_POST["terms"]);

    if (!preg_match("/^[A-Za-z ]{3,}$/", $name)) {
        $errors[] = "Enter a valid name.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        $errors[] = "Enter a valid 10 digit mobile number.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password != $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if ($course == "") {
        $errors[] = "Please select a course.";
    }

    if ($year == "") {
        $errors[] = "Please select your year.";
    }

    if ($gender == "") {
        $errors[] = "Please select your gender.";
    }

    if (!$terms) {
        $errors[] = "Please accept the terms and conditions.";
    }

    if (count($errors) == 0) {
        $student = [
            "name" => $name,
            "email" => $email,
            "mobile" => $mobile,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "course" => $course,
            "year" => $year,
            "gender" => $gender
        ];

        $file = "registrations.json";

        if (file_exists($file)) {
            $oldData = json_decode(file_get_contents($file), true);

            if (!is_array($oldData)) {
                $oldData = [];
            }
        } else {
            $oldData = [];
        }

        $oldData[] = $student;

        if (file_put_contents($file, json_encode($oldData, JSON_PRETTY_PRINT))) {
            $success = "Registration successful!";
        } else {
            $errors[] = "Unable to save registration data.";
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
    <link rel="stylesheet" href="style.css">
</head>
<body class="body">
    <div class="maindiv">
        <div id="divregister1">
            <h1 style="text-align: center; background-color: #29524A;">Student Hub</h1>
            <p>
                <ul class="list">
                    <li>Lecture Attendance</li>
                    <li>Lecture Time-table</li>
                    <li>University Result</li>
                    <li>E-Governance</li>
                    <li>Fees Payment</li>
                </ul>
            </p>
        </div>

        <div id="divregister2">
            <form class="form" id="registerForm" method="POST" novalidate>
                <h2>Student Registration</h2>

                <?php
                    foreach ($errors as $error) {
                        echo "<p style='color: darkred;'>" . htmlspecialchars($error) . "</p>";
                    }

                    if ($success != "") {
                        echo "<p style='color: darkgreen;'>" . htmlspecialchars($success) . "</p>";
                    }
                ?>
                
                <label for="name">Full Name:</label><br>
                <input type="text" id="name" name="name" placeholder="Enter your full name">
                <span class="error" id="nameError"></span>
                <br>

                <label for="email">Email:</label><br>
                <input type="email" id="email" name="email" placeholder="Enter your email">
                <span class="error" id="emailError"></span>
                <br>

                <label for="mobile">Mobile Number:</label><br>
                <input type="tel" id="mobile" name="mobile" placeholder="Enter 10 digit mobile number">
                <span class="error" id="mobileError"></span>
                <br>

                <label for="password">Password:</label><br>
                <input type="password" id="password" name="password" placeholder="Enter password">
                <span class="error" id="passwordError"></span>
                <br>

                <label for="confirmPassword">Confirm Password:</label><br>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password">
                <span class="error" id="confirmPasswordError"></span>
                <br>

                <label for="course">Course:</label><br>
                <select id="course" name="course">
                    <option value="">Select Course</option>
                    <option value="CSE">Computer Science</option>
                    <option value="IT">Information Technology</option>
                    <option value="CE">Computer Engineering</option>
                    <option value="Other">Other</option>
                </select>
                <span class="error" id="courseError"></span>
                <br>

                <label for="year">Year:</label><br>
                <select id="year" name="year">
                    <option value="">Select Year</option>
                    <option value="1">First Year</option>
                    <option value="2">Second Year</option>
                    <option value="3">Third Year</option>
                    <option value="4">Fourth Year</option>
                </select>
                <span class="error" id="yearError"></span>
                <br>

                <label>Gender:</label><br>

                <input type="radio" name="gender" id="male" value="Male">
                <label for="male">Male</label>

                <input type="radio" name="gender" id="female" value="Female">
                <label for="female">Female</label>

                <input type="radio" name="gender" id="other" value="Other">
                <label for="other">Other</label>

                <br>
                <span class="error" id="genderError"></span>
                <br>

                <input type="checkbox" id="terms" name="terms">
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