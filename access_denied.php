<?php

require_once __DIR__ . '/auth/check_auth.php';

http_response_code(403);

include __DIR__ . '/includes/header.php';

?>

<div class="layout">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

<div class="pcms-access-denied" role="alert">
    <span class="pcms-access-denied-icon" aria-hidden="true">
        <i class="fas fa-shield-halved"></i>
    </span>
    <p class="section-heading">Access Restricted</p>
    <h1>Administrator access is required</h1>
    <p>
        Your account can use the Property Custodian modules, but only a
        System Administrator can manage user accounts and access.
    </p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>dashboard.php">
        Return to Dashboard
    </a>
</div>

</main>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
