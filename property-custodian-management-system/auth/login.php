<?php

session_start();

if (isset($_SESSION['user'])) {

    header("Location: ../dashboard.php");
    exit();

}

$error = "";

if (isset($_GET['error'])) {
    $error = "Invalid Employee ID or Password.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | Property Custodian Management System</title>

<link rel="stylesheet" href="login.css">

</head>

<body>

<div class="login-container">

    <div class="login-card">

    <div class="logo-placeholder">

    School Logo

    </div>


    
        <h1>PROPERTY CUSTODIAN</h1>

        <h2>MANAGEMENT SYSTEM</h2>

        <p>School Asset Monitoring & Accountability</p>

        <?php if($error!=""): ?>

            <div class="error-message">

                <?= $error ?>

            </div>

        <?php endif; ?>

        <form method="POST" action="authenticate.php">

            <label>Employee ID</label>

            <input
                type="text"
                name="employee_id"
                required>

            <label>Password</label>

            <input
                type="password"
                name="password"
                required>

            <div class="remember">

                <label>

                    <input
                        type="checkbox"
                        name="remember">

                    Remember Me

                </label>

            </div>

            <button
                class="btn btn-primary"
                type="submit">

                Sign In

            </button>

        </form>

        <br>

        <a href="forgot_password.php">

            Forgot Password?

        </a>

    </div>

</div>

</body>

</html>