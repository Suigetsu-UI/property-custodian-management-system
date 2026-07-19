<?php

require_once "../../auth/check_auth.php";

include "../../includes/header.php";

?>

<div class="layout">

    <?php include "../../includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Add Procurement</h1>

        <hr>

        <?php include "procurement_form.php"; ?>

    </div>

</div>

<?php include "../../includes/footer.php"; ?>