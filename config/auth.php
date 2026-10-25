<?php
/**
 * Authentication & Session Management
 */

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login; redirect to login.php if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: /MeatShop/login.php");
        exit;
    }
}

// Check permission
function has_permission($perm_key) {
    if (!is_logged_in()) {
        return false;
    }
    // Admin has all permissions
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin') {
        return true;
    }
    $perms = $_SESSION['permissions'] ?? [];
    return in_array($perm_key, $perms);
}

// Require permission
function require_permission($perm_key) {
    require_login();
    if (!has_permission($perm_key)) {
        header("Location: /MeatShop/unauthorized.php");
        exit;
    }
}

// Current logged in user info
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_user_fullname() {
    return $_SESSION['full_name'] ?? 'Guest User';
}

function get_current_role_name() {
    return $_SESSION['role_name'] ?? '';
}

function get_current_branch_id() {
    return $_SESSION['branch_id'] ?? 1;
}

// Set active branch (for multi-branch switching)
function set_current_branch($branch_id) {
    $_SESSION['branch_id'] = (int)$branch_id;
}
