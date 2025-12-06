<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Call at top of any protected page (dashboard, buildings, units, tenants, etc.)
 */
function require_owner_login(): void
{
    if (empty($_SESSION['owner_id'])) {
        header('Location: login.php');
        exit;
    }
}
