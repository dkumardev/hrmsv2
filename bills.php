<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if ($assignmentId <= 0) {
    header('Location: units.php');
    exit;
}

// Fetch assignment + tenant + unit info
$stmt = $conn->prepare('
    SELECT ua.*,
           u.unit_name, u.floor, u.unit_type,
           b.name AS building_name,
           t.full_name AS tenant_name, t.phone_number
    FROM unit_assignments ua
    JOIN units u     ON ua.unit_id = u.id
    JOIN buildings b ON ua.building_id = b.id
    JOIN tenants t   ON ua.tenant_id = t.id
    WHERE ua.id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$resAssign = $stmt->get_result();
$assignment = $resAssign->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header('Location: units.php');
    exit;
}

$message = '';

// Handle new bill submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bill'])) {
    $billType     = trim($_POST['bill_type'] ?? 'Electricity');
    $billMonth    = $_POST['bill_month'] ?? '';
    $prevReading  = $_POST['prev_reading'] !== '' ? (float)$_POST['prev_reading'] : null;
    $currReading  = $_POST['curr_reading'] !== '' ? (float)$_POST['curr_reading'] : null;
    $ratePerUnit  = $_POST['rate_per_unit'] !== '' ? (float)$_POST['rate_per_unit'] : null;
    $amountInput  = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
    $notes        = trim($_POST['notes'] ?? '');

    if (!empty($billMonth)) {
        // Calculate amount if not manually provided and meter data exists
        $amount = $amountInput;
        if ($amount === null && $prevReading !== null && $currReading !== null && $ratePerUnit !== null) {
            $units  = $currReading - $prevReading;
            if ($units < 0) {
                $message = 'Current reading cannot be less than previous reading.';
            } else {
                $amount = $units * $ratePerUnit;
            }
        }

        if ($amount === null) {
            $message = $message ?: 'Please enter amount or complete meter readings and rate.';
        } else {
            $stmt = $conn->prepare('
                INSERT INTO bills
                    (assignment_id, bill_type, bill_month, prev_reading, curr_reading, rate_per_unit, amount, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param(
                'isssddds',
                $assignmentId,
                $billType,
                $billMonth,
                $prevReading,
                $currReading,
                $ratePerUnit,
                $amount,
                $notes
            );
            if ($stmt->execute()) {
                $message = 'Bill added successfully.';
            } else {
                $message = 'Error adding bill.';
            }
            $stmt->close();
        }
    } else {
        $message = 'Bill month is required.';
    }
}

// Fetch bills for this assignment
$stmt = $conn->prepare('
    SELECT id, bill_type, bill_month, prev_reading, curr_reading, rate_per_unit, amount, notes, created_at
    FROM bills
    WHERE assignment_id = ?
    ORDER BY bill_month DESC, id DESC
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$resBills = $stmt->get_result();
$bills = [];
while ($row = $resBills->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

$pageTitle = 'Bills - ' . $assignment['tenant_name'];
include 'header.php';
?>
<link rel="stylesheet" href="assign_tenant.css">
<link rel="stylesheet" href="ledger.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Bills</h1>
                    <p class="page-subtitle">
                        <?php echo htmlspecialchars($assignment['tenant_name']); ?> ·
                        <?php echo htmlspecialchars($assignment['building_name'] . ' - ' . $assignment['unit_name']); ?>
                    </p>
                </div>
                <div>
                    <a href="tenant_profile.php?assignment_id=<?php echo (int)$assignmentId; ?>" class="btn btn-outline-small">
                        ← Back to Tenant Profile
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="dashboard-tables">
                <!-- LEFT: Bills list -->
                <div class="dashboard-table">
                    <h4>Existing Bills</h4>
                    <?php if (empty($bills)): ?>
                        <p class="empty-text">No bills added yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Month</th>
                                <th>Type</th>
                                <th>Prev</th>
                                <th>Curr</th>
                                <th>Units</th>
                                <th>Rate</th>
                                <th>Amount (₹)</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bills as $bill): ?>
                                <?php
                                $units = null;
                                if ($bill['prev_reading'] !== null && $bill['curr_reading'] !== null) {
                                    $units = $bill['curr_reading'] - $bill['prev_reading'];
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['bill_month']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['bill_type']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['prev_reading']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['curr_reading']); ?></td>
                                    <td><?php echo $units !== null ? htmlspecialchars($units) : ''; ?></td>
                                    <td><?php echo htmlspecialchars($bill['rate_per_unit']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($bill['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($bill['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Add bill form -->
                <div class="dashboard-table">
                    <h4>Add New Bill</h4>
                    <form method="post" class="form-vertical">
                        <div class="form-group">
                            <label for="bill_type">Bill Type</label>
                            <select id="bill_type" name="bill_type">
                                <option value="Electricity">Electricity</option>
                                <option value="Water">Water</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="bill_month">Bill Month</label>
                            <input type="date" id="bill_month" name="bill_month" required>
                        </div>

                        <div class="form-group">
                            <label>Meter Readings (optional)</label>
                            <div style="display:flex; gap:0.5rem;">
                                <input type="number" step="0.01" min="0" name="prev_reading" placeholder="Previous">
                                <input type="number" step="0.01" min="0" name="curr_reading" placeholder="Current">
                                <input type="number" step="0.01" min="0" name="rate_per_unit" placeholder="Rate">
                            </div>
                            <small>If amount is empty, system uses (Curr − Prev) × Rate.</small>
                        </div>

                        <div class="form-group">
                            <label for="amount">Amount (₹)</label>
                            <input type="number" step="0.01" min="0" id="amount" name="amount"
                                   placeholder="Leave blank to auto-calc from meter">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" placeholder="Any extra info"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="add_bill" class="btn btn-primary">Add Bill</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
