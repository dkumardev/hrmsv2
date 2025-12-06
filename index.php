<?php
$pageTitle = 'MyProperty Manager - Track rent, electricity, payments';
include 'header.php';
?>
<link rel="stylesheet" href="index.css">

<main class="main-content">

    <!-- HERO -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="hero-text">
                <h1>Track rent, electricity bills, partial payments and dues</h1>
                <p>Everything you need in one place for rooms and shops across multiple buildings.</p>
                <div class="hero-actions">
                    <a href="login.php" class="btn btn-primary btn-large">
                        Get Started – Owner Login
                    </a>
                </div>
            </div>
            <div class="hero-side">
                <div class="hero-card">
                    <h3>Real-time overview</h3>
                    <p>Rent added/paid, bill added/paid, dues and last payments at a glance.</p>
                </div>
                <div class="hero-card">
                    <h3>Smart electricity</h3>
                    <p>(Current − Previous) × Rate, with carry‑forward of pending bill.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- KEY FEATURES -->
    <section class="section section-light">
        <div class="container">
            <div class="section-header">
                <h2>Key Features</h2>
                <p>From setup to collections for rooms and shops with private meters.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">🏢</div>
                    <h3>Buildings</h3>
                    <p>Add buildings with addresses and organize your entire portfolio.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🏠</div>
                    <h3>Units</h3>
                    <p>Configure floor, type, rent amount, size and availability.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Tenants</h3>
                    <p>Assign tenants with lease details, deposit and ID proofs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- WORKFLOW SUMMARY -->
    <section class="section section-muted">
        <div class="container">
            <div class="section-header">
                <h2>From setup to collections</h2>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <h4>1. Configure portfolio</h4>
                    <p>Add buildings and configure rooms/shops with rent and status.</p>
                </div>
                <div class="step-card">
                    <h4>2. Add tenants & leases</h4>
                    <p>Create tenant profile, lease start, monthly rent, deposit and starting meter.</p>
                </div>
                <div class="step-card">
                    <h4>3. Track rent & bills</h4>
                    <p>Add monthly rent due and meter readings; the system calculates electricity bill.</p>
                </div>
                <div class="step-card">
                    <h4>4. Record payments</h4>
                    <p>Split payments into rent and bill, update dues and carry forward balances.</p>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include 'footer.php'; ?>
