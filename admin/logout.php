<?php
// ----------------------------------------------------------------
// --- Admin Logout Script ---
// ----------------------------------------------------------------

// Always start the session to access session variables.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset only the admin-specific session variables.
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);

// Destroy the session if no other session data is needed (e.g., for the user part).
// For this project, it's safe to destroy it completely.
session_destroy();

// Redirect to the admin login page.
header("Location: index.php");
exit();
?>
