<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['assets'])) {
    $_SESSION['assets'] = [];
}

function generateAssetID()
{
    return "AST-" . str_pad(count($_SESSION['assets']) + 1, 6, "0", STR_PAD_LEFT);
}


if (!isset($_SESSION['inventory'])) {
    $_SESSION['inventory'] = [];
}

function generateInventoryID()
{
    return "INV-" . str_pad(count($_SESSION['inventory']) + 1, 6, "0", STR_PAD_LEFT);
}