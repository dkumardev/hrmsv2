<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$loggedIn = isset($_SESSION['owner_id']);
$pageTitle = $pageTitle ?? 'MyProperty Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <script src="header.js" defer></script>
</head>
<body>
<header class="header">
    <div class="container header-content">
        <div class="logo">
            <h2>MyPropertyManager</h2>
        </div>

        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>

        <nav class="nav" id="mainNav">
            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="buildings.php" class="nav-link">Buildings</a>
                <a href="units.php" class="nav-link">Units</a>
                <a href="tenants.php" class="nav-link">Tenants</a>
            <?php else: ?>
                <a href="index.php" class="nav-link active">Home</a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <?php if ($loggedIn): ?>
                <span class="header-user">
                    <?php echo htmlspecialchars($_SESSION['owner_name'] ?? $_SESSION['owner_username']); ?>
                </span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Owner Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>
