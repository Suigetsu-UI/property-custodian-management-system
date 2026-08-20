<?php

require_once "../../auth/check_auth.php";

/*
 * Reports are generated dynamically from persistent operational
 * PostgreSQL data. There is no Reports business table or stored
 * report record to delete.
 */
header("Location: index.php");
exit;