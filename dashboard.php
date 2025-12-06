<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

// summary counts
$buildingsCount = (int)$conn->query("SELECT COUNT(*) AS c FROM buildings")->fetch_assoc()['c'];
$unitsCount     = (int)$conn->query("SELECT COUNT(*) AS c FROM units")->fetch_assoc()['c'];
$tenantsCount   = (int)$conn->query("SELECT COUNT(*) AS c FROM tenants")->fetch_assoc()['c'];

$pageTitle = 'Owner Dashboard - MyProperty Manager';
include 'header.php';
?>
<link rel="stylesheet" href="dashboard.css">

<main class="main-content">
    <section class="dashboard-hero">
        <div class="container">
            <div class="dashboard-hero-inner">
                <div class="dashboard-hero-text">
                    <h1>Owner Dashboard</h1>
                    <p>Manage buildings, units, tenants, rent and electricity from one place.</p>
                    <p class="dashboard-hero-sub">
                        Rent due &amp; history, electricity readings, and payments – all connected.
                    </p>
                </div>
                <div class="dashboard-hero-cards">
                    <div class="summary-card">
                        <div class="summary-label">Buildings</div>
                        <div class="summary-value"><?php echo $buildingsCount; ?></div>
                        <div class="summary-actions">
                            <a href="buildings.php">Manage buildings</a>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Units</div>
                        <div class="summary-value"><?php echo $unitsCount; ?></div>
                        <div class="summary-actions">
                            <a href="units.php">Manage units</a>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Tenants</div>
                        <div class="summary-value"><?php echo $tenantsCount; ?></div>
                        <div class="summary-actions">
                            <a href="tenants.php">Manage tenants</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-light">
        <div class="container">
            <div class="section-header">
                <h2>Quick actions</h2>
                <p>Jump to the main modules that you work with every day.</p>
            </div>

            <div class="dashboard-actions-grid">
                <div class="dashboard-action-card">
                    <h3>Buildings</h3>
                    <p>Add and edit buildings with their addresses.</p>
                    <a href="buildings.php" class="btn btn-secondary btn-outline-small">Go to buildings</a>
                </div>
                <div class="dashboard-action-card">
                    <h3>Units</h3>
                    <p>Configure rooms/shops, floors, types, rent and amenities.</p>
                    <a href="units.php" class="btn btn-secondary btn-outline-small">Go to units</a>
                </div>
                <div class="dashboard-action-card">
                    <h3>Tenants</h3>
                    <p>Create tenant profiles, update details and ID proofs.</p>
                    <a href="tenants.php" class="btn btn-secondary btn-outline-small">Go to tenants</a>
                </div>
                <div class="dashboard-action-card">
                    <h3>Assign tenants</h3>
                    <p>Assign tenants to units with rent, deposit and meter start.</p>
                    <a href="units.php" class="btn btn-secondary btn-outline-small">Assign from units</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-muted">
        <div class="container">
            <div class="section-header">
                <h2>Coming next</h2>
                <p>Plan for rent, electricity, and payment tracking modules.</p>
            </div>
            <div class="dashboard-roadmap-grid">
                <div class="roadmap-item">
                    <h4>Rent due &amp; history</h4>
                    <p>Create monthly rent, see due per tenant and mark payments.</p>
                </div>
                <div class="roadmap-item">
                    <h4>Electricity readings</h4>
                    <p>Record readings, calculate bills and carry forward pending amounts.</p>
                </div>
                <div class="roadmap-item">
                    <h4>Payments</h4>
                    <p>Split one payment into rent and electricity, with full ledger.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
