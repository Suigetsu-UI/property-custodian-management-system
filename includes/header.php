<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/access_control.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <title>Property Custodian Management System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
    <script src="<?= BASE_URL ?>assets/js/pcms_modal.js"></script>

</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<header class="top-header">

    <div class="top-header-left">
        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Hide navigation"
            aria-controls="sidebar"
            aria-expanded="true"
            title="Hide navigation"
        >
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <div class="system-title">
            Property Custodian Management System
        </div>
    </div>

    <div class="top-header-right">
        <div class="header-date">
            <div class="header-date-label">Today</div>
            <div class="header-date-value" id="headerDate"></div>
        </div>
        <?php
        $headerUser = $_SESSION['user'] ?? [];
        $headerRole = userRoleLabel($headerUser['role'] ?? '');
        $headerInitial = strtoupper(substr($headerRole, 0, 1));
        ?>
        <div class="header-avatar" aria-hidden="true"><?= htmlspecialchars($headerInitial) ?></div>
        <div class="user-info">
            Welcome, <?= htmlspecialchars($headerRole) ?>
        </div>
    </div>

</header>
