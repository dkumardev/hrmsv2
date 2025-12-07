<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

$unitId = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

if ($unitId <= 0) {
    header('Location: units.php?msg=no_unit');
    exit;
}

// ----- FETCH UNIT -----
$stmt = $conn->prepare('
    SELECT u.*, b.name AS building_name 
    FROM units u 
    LEFT JOIN buildings b ON u.building_id = b.id 
    WHERE u.id = ?
');
$stmt->bind_param('i', $unitId);
$stmt->execute();
$result = $stmt->get_result();
$unit = $result->fetch_assoc();
$stmt->close();

if (!$unit) {
    header('Location: units.php?msg=no_unit');
    exit;
}

// ----- CHECK CURRENT ASSIGNMENT -----
$currentAssignment = null;
$stmt = $conn->prepare('
    SELECT ua.*, t.full_name AS tenant_fullname 
    FROM unit_assignments ua 
    LEFT JOIN tenants t ON ua.tenant_id = t.id 
    WHERE ua.unit_id = ? AND ua.end_date IS NULL 
    ORDER BY ua.start_date DESC 
    LIMIT 1
');
$stmt->bind_param('i', $unitId);
$stmt->execute();
$result = $stmt->get_result();
$currentAssignment = $result->fetch_assoc();
$stmt->close();

// ----- MESSAGES -----
$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'assigned') $message = 'Tenant assigned successfully.';
    if ($_GET['msg'] === 'ended')    $message = 'Tenancy ended successfully.';
}

// ----- END TENANCY -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_tenancy'])) {
    $endDate   = date('Y-m-d');
    $endRemarks = trim($_POST['end_remarks'] ?? '');

    $stmt = $conn->prepare('
        UPDATE unit_assignments 
        SET end_date = ?, 
            remarks = CONCAT(COALESCE(remarks, ""), "\n---\n", ?) 
        WHERE unit_id = ? AND end_date IS NULL
    ');
    $stmt->bind_param('ssi', $endDate, $endRemarks, $unitId);

    if ($stmt->execute()) {
        $stmt->close();

        // Update unit status back to Vacant
        $stmt = $conn->prepare('UPDATE units SET status = "Vacant" WHERE id = ?');
        $stmt->bind_param('i', $unitId);
        $stmt->execute();
        $stmt->close();

        header('Location: assign_tenant.php?unit_id=' . $unitId . '&msg=ended');
        exit;
    }

    $stmt->close();
    $message = 'Error ending tenancy.';
}

// ----- ASSIGN TENANT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tenant'])) {
    $tenantId            = (int)$_POST['tenant_id'];
    $startDate           = $_POST['start_date'];
    $monthlyRent         = (float)$_POST['monthly_rent'];
    $advanceDeposit      = (float)$_POST['advance_deposit'];
    $initialMeterReading = !empty($_POST['initial_meter_reading']) ? (int)$_POST['initial_meter_reading'] : null;
    $remarks             = trim($_POST['remarks'] ?? '');

    if ($tenantId > 0 && $monthlyRent > 0 && !empty($startDate)) {
        $buildingId = $unit['building_id'];

        $stmt = $conn->prepare('
            INSERT INTO unit_assignments 
            (building_id, unit_id, tenant_id, start_date, monthly_rent, advance_deposit, initial_meter_reading, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param(
            'iiisddis',
            $buildingId,
            $unitId,
            $tenantId,
            $startDate,
            $monthlyRent,
            $advanceDeposit,
            $initialMeterReading,
            $remarks
        );

        if ($stmt->execute()) {
            $assignmentId = $stmt->insert_id;
            $stmt->close();

            // Create first rent_items row: month_start = start_date, month_end = +1 month
            try {
                $monthStart = new DateTime($startDate);
                $monthEnd   = (clone $monthStart)->modify('+1 month');

                $ms = $monthStart->format('Y-m-d');
                $me = $monthEnd->format('Y-m-d');

                $stmtRent = $conn->prepare('
                    INSERT INTO rent_items (assignment_id, month_start, month_end, amount)
                    VALUES (?, ?, ?, ?)
                ');
                $stmtRent->bind_param('issd', $assignmentId, $ms, $me, $monthlyRent);
                $stmtRent->execute();
                $stmtRent->close();
            } catch (Exception $e) {
                // Optional: log $e->getMessage()
            }

            // Update unit status to Occupied
            $stmt = $conn->prepare('UPDATE units SET status = "Occupied" WHERE id = ?');
            $stmt->bind_param('i', $unitId);
            $stmt->execute();
            $stmt->close();

            header('Location: units.php?msg=assigned');
            exit;
        }

        $stmt->close();
        $message = 'Error assigning tenant.';
    } else {
        $message = 'Please select tenant, start date and enter monthly rent.';
    }
}

// ----- FETCH ASSIGNABLE TENANTS (New → Active → Inactive) -----
$assignableTenants = [];
$result = $conn->query("
    SELECT id, full_name, phone_number, tenant_status
    FROM tenants
    ORDER BY
        CASE tenant_status
            WHEN 'New' THEN 1
            WHEN 'Active' THEN 2
            WHEN 'Inactive' THEN 3
        END,
        full_name
");
while ($row = $result->fetch_assoc()) {
    $assignableTenants[] = $row;
}

$pageTitle = 'Assign Tenant - Unit ' . $unit['unit_name'];
include 'header.php';
?>
<link rel="stylesheet" href="assign_tenant.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Assign Tenant</h1>
                    <p class="page-subtitle">
                        Unit: <?php echo htmlspecialchars($unit['building_name'] . ' - ' . $unit['unit_name']); ?>
                    </p>
                </div>
                <div>
                    <a href="units.php" class="btn btn-outline-small">← Back to Units</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- LEFT: Unit / current assignment -->
                <div class="section">
                    <h2 class="section-title">Unit Details</h2>
                    <p><strong>Unit:</strong> <?php echo htmlspecialchars($unit['unit_name']); ?></p>
                    <p><strong>Floor:</strong> <?php echo htmlspecialchars($unit['floor']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($unit['unit_type']); ?></p>
                    <?php if (!empty($unit['amenities'])): ?>
                        <p><strong>Amenities:</strong> <?php echo htmlspecialchars($unit['amenities']); ?></p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($unit['status']); ?></p>

                    <hr>

                    <h3 class="section-title">Current Tenant</h3>
                    <?php if ($currentAssignment): ?>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($currentAssignment['tenant_fullname']); ?></p>
                        <p><strong>Since:</strong> <?php echo htmlspecialchars($currentAssignment['start_date']); ?></p>
                        <p><strong>Rent:</strong> ₹<?php echo htmlspecialchars($currentAssignment['monthly_rent']); ?></p>

                        <form method="post" class="form-vertical" style="margin-top: 1rem;">
                            <h4 class="section-title">End Current Tenancy</h4>
                            <div class="form-group">
                                <label for="end_remarks">End Remarks</label>
                                <textarea id="end_remarks" name="end_remarks" rows="2"></textarea>
                            </div>
                            <button type="submit" name="end_tenancy" class="btn btn-danger"
                                    onclick="return confirm('End current tenancy and vacate unit?');">
                                End Tenancy
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="empty-text">No active tenant currently assigned to this unit.</p>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Assign new tenant -->
                <div class="section">
                    <h2 class="section-title">Assign New Tenant</h2>

                    <?php if (empty($assignableTenants)): ?>
                        <p class="empty-text">
                            No tenants available. Create tenants first on the
                            <a href="tenants.php">Tenants</a> page.
                        </p>
                    <?php else: ?>
                        <form method="post" class="form-vertical">
                            <div class="form-group">
                                <label for="tenant_id">Select Tenant</label>
                                <select id="tenant_id" name="tenant_id" required>
                                    <option value="">Choose tenant...</option>
                                    <?php foreach ($assignableTenants as $t): ?>
                                        <option value="<?php echo (int)$t['id']; ?>">
                                            <?php echo htmlspecialchars($t['full_name']); ?>
                                            (<?php echo htmlspecialchars($t['tenant_status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="monthly_rent">Monthly Rent (₹)</label>
                                <input type="number" id="monthly_rent" name="monthly_rent"
                                       step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label for="advance_deposit">Advance Deposit (₹)</label>
                                <input type="number" id="advance_deposit" name="advance_deposit"
                                       step="0.01" min="0">
                            </div>

                            <div class="form-group">
                                <label for="initial_meter_reading">Initial Meter Reading</label>
                                <input type="number" id="initial_meter_reading" name="initial_meter_reading"
                                       step="1" min="0">
                            </div>

                            <div class="form-group">
                                <label for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" rows="2"></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="assign_tenant" class="btn btn-primary">
                                    Assign Tenant
                                </button>
                                <a href="units.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
