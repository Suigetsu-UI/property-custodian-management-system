<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/access_control.php';

startPcmsSession();

if (isset($_SESSION['user'])) {
    header('Location: ../dashboard.php');
    exit();
}

$error = match ($_GET['error'] ?? '') {
    'inactive' => 'This account is inactive. Contact the System Administrator.',
    'session' => 'Your session could not be verified. Please sign in again.',
    'expired' => 'Your session expired. Please sign in again.',
    'invalid', '1' => 'Invalid Employee ID or Password.',
    default => '',
};

require_once __DIR__ . '/../config/config.php';

$loginCss = BASE_URL . 'auth/login.css?v=20260729';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Property Custodian Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($loginCss) ?>">
</head>
<body class="pcms-login">

<div class="login-backdrop" aria-hidden="true">
    <span class="login-orb login-orb--1"></span>
    <span class="login-orb login-orb--2"></span>
    <span class="login-orb login-orb--3"></span>
</div>

<main class="login-shell">
    <section class="login-card" aria-labelledby="login-title">

        <div class="login-card-top">
            <div class="login-logo-badge">
                <img
                    src="<?= BASE_URL ?>assets/images/logo.png"
                    alt="St. Agnes Academy of Caloocan Inc."
                    class="login-logo"
                    width="64"
                    height="64"
                    decoding="async">
            </div>
            <p class="login-eyebrow">Property Custodian</p>
            <h1 id="login-title" class="login-title">Management System</h1>
            <p class="login-subtitle">School asset monitoring &amp; accountability</p>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        <div class="login-card-body">
            <h2 class="login-form-heading">Sign in to your account</h2>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="authenticate.php" class="login-form" novalidate>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="login-field">
                    <label for="employee_id">Employee ID</label>
                    <div class="login-input">
                        <i class="fas fa-id-badge" aria-hidden="true"></i>
                        <input
                            id="employee_id"
                            type="text"
                            name="employee_id"
                            placeholder="Enter employee ID"
                            autocomplete="username"
                            required>
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="login-input">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required>
                    </div>
                </div>

                <div class="login-options">
                    <label class="login-remember">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="login-submit">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>

        <p class="login-footnote">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            Authorized personnel only
        </p>
    </section>
</main>

</body>
</html>
