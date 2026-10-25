<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$page_title = $page_title ?? 'Meat Shop Management';
$current_branch_id = get_current_branch_id();

// Fetch branches for switch selector
$branches_stmt = $pdo->query("SELECT id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY is_main DESC, branch_name ASC");
$all_branches = $branches_stmt->fetchAll();

// Fetch active shift status
$active_shift = get_active_shift($pdo, $current_branch_id, get_current_user_id());
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(get_setting('shop_name', 'Meat Shop System')) ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom Theme Styling -->
    <style>
        :root {
            --meat-crimson: #991b1b;
            --meat-crimson-dark: #7f1d1d;
            --meat-crimson-light: #fef2f2;
            --meat-accent: #dc2626;
            --meat-amber: #d97706;
            --meat-dark: #0f172a;
            --meat-slate: #1e293b;
            --meat-gray-bg: #f8fafc;
            --meat-border: #e2e8f0;
            --meat-sidebar-width: 260px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--meat-gray-bg);
            color: #334155;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--meat-sidebar-width);
            height: 100vh;
            max-height: 100vh;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040;
            background: linear-gradient(180deg, #1e1b18 0%, #0f172a 100%);
            color: #f1f5f9;
            transition: all 0.25s ease-in-out;
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(220, 38, 38, 0.4) transparent;
        }

        #sidebar::-webkit-scrollbar {
            width: 5px;
        }
        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: #dc2626;
        }

        /* Desktop Collapsed Sidebar State */
        body.sidebar-collapsed #sidebar {
            margin-left: calc(-1 * var(--meat-sidebar-width));
        }
        body.sidebar-collapsed #content-wrapper {
            margin-left: 0;
        }

        #sidebar .brand-header {
            padding: 1.25rem 1.2rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #sidebar .brand-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4);
        }

        #sidebar .nav-link {
            color: #cbd5e1;
            padding: 0.65rem 1.25rem;
            font-size: 0.88rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.2s;
            text-decoration: none;
        }

        #sidebar .nav-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(3px);
        }

        #sidebar .nav-link.active {
            color: #ffffff;
            background: linear-gradient(90deg, #dc2626, #b91c1c);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
            font-weight: 600;
        }

        #sidebar .nav-link i {
            width: 20px;
            font-size: 1rem;
            text-align: center;
        }

        #sidebar .nav-header {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 1rem 1.25rem 0.35rem;
            font-weight: 700;
        }

        /* Main Content Layout */
        #content-wrapper {
            margin-left: var(--meat-sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease-in-out;
        }

        /* Top Navbar */
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--meat-border);
            padding: 0.75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        /* Cards & Components */
        .card-custom {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--meat-border);
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .card-custom:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }

        .card-header-clean {
            background: transparent;
            border-bottom: 1px solid var(--meat-border);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-meat {
            background: #dc2626;
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-meat:hover, .btn-meat:focus {
            background: #b91c1c;
            color: white;
        }

        .badge-subtle-success {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
        }

        .badge-subtle-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
        }

        .badge-subtle-warning {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 600;
        }

        .badge-subtle-info {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
        }

        /* Overlay for Mobile Drawer */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
            z-index: 1035;
        }
        .sidebar-overlay.show {
            display: block;
        }

        /* Mobile Bottom Nav Bar */
        .mobile-bottom-nav {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.96) !important;
        }

        /* Touch / Mobile Optimizations */
        @media (max-width: 991.98px) {
            #sidebar {
                margin-left: calc(-1 * var(--meat-sidebar-width));
            }
            #sidebar.show {
                margin-left: 0;
            }
            #content-wrapper {
                margin-left: 0 !important;
                padding-bottom: 75px; /* Space for mobile bottom nav bar */
            }
            /* Prevent iOS auto-zoom on input focus */
            input:not([type="checkbox"]):not([type="radio"]), select, textarea {
                font-size: 16px !important;
            }
            /* Compact table cells on small devices */
            .table > :not(caption) > * > * {
                padding: 0.55rem 0.5rem;
            }
        }

        /* Quick modal trigger link */
        .quick-add-btn {
            cursor: pointer;
            color: #dc2626;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 600;
        }
        .quick-add-btn:hover {
            color: #991b1b;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
