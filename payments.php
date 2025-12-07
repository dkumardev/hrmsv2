<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

function allocate_payment(mysqli $conn, int $paymentId, int $assignmentId, float $amount): void
{
    if ($amount <= 0) {
        return;
    }

    // 1) Get all rent items with remaining due > 0 (oldest first)
    $sqlRent = "
        SELECT ri.id, ri.amount,
               COALESCE(SUM(pa.amount), 0) AS allocated
        FROM rent_items ri
        LEFT JOIN payment_allocations pa
            ON pa.item_type = 'RENT'
           AND pa.item_id   = ri.id
        WHERE ri.assignment_id = ?
        GROUP BY ri.id
        HAVING ri.amount > COALESCE(SUM(pa.amount), 0)
        ORDER BY ri.month_start ASC
    ";
    $stmt = $conn->prepare($sqlRent);
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $resRent = $stmt->get_result();
    $rentItems = [];
    while ($row = $resRent->fetch_assoc()) {
        $rentItems[] = $row;
    }
    $stmt->close();

    // 2) Get all bills with remaining due > 0 (oldest first)
    $sqlBills = "
        SELECT b.id, b.amount,
               COALESCE(SUM(pa.amount), 0) AS allocated
        FROM bills b
        LEFT JOIN payment_allocations pa
            ON pa.item_type = 'BILL'
           AND pa.item_id   = b.id
        WHERE b.assignment_id = ?
        GROUP BY b.id
        HAVING b.amount > COALESCE(SUM(pa.amount), 0)
        ORDER BY b.bill_month ASC, b.id ASC
    ";
    $stmt = $conn->prepare($sqlBills);
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $resBills = $stmt->get_result();
    $billItems = [];
    while ($row = $resBills->fetch_assoc()) {
        $billItems[] = $row;
    }
    $stmt->close();

    // 3) Allocate to rent first, then bills
    $remaining = $amount;

    $stmtAlloc = $conn->prepare('
        INSERT INTO payment_allocations (payment_id, item_type, item_id, amount)
        VALUES (?, ?, ?, ?)
    ');

    // Allocate to rent
    foreach ($rentItems as $ri) {
        if ($remaining <= 0) break;
        $due = (float)$ri['amount'] - (float)$ri['allocated'];
        if ($due <= 0) continue;

        $alloc = min($remaining, $due);
        $itemType = 'RENT';
        $itemId   = (int)$ri['id'];
        $stmtAlloc->bind_param('isid', $paymentId, $itemType, $itemId, $alloc);
        $stmtAlloc->execute();

        $remaining -= $alloc;
    }

    // Allocate to bills
    foreach ($billItems as $bi) {
        if ($remaining <= 0) break;
        $due = (float)$bi['amount'] - (float)$bi['allocated'];
        if ($due <= 0) continue;

        $alloc = min($remaining, $due);
        $itemType = 'BILL';
        $itemId   = (int)$bi['id'];
        $stmtAlloc->bind_param('isid', $paymentId, $itemType, $itemId, $alloc);
        $stmtAlloc->execute();

        $remaining -= $alloc;
    }

    $stmtAlloc->close();
}


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
           t.id AS tenant_id,
           t.full_name AS tenant_name, 
           t.phone_number
    FROM unit_assignments ua
    JOIN units u     ON ua.unit_id = u.id
    JOIN buildings b ON ua.building_id = b.id
    JOIN tenants t   ON ua.tenant_id = t.id
    WHERE ua.id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$resAssign  = $stmt->get_result();
$assignment = $resAssign->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header('Location: units.php');
    exit;
}

$tenantId = (int)$assignment['tenant_id'];
$message  = '';

// Handle new payment submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $paymentDate = $_POST['payment_date'] ?? '';
    $amount      = $_POST['amount'] !== '' ? (float)$_POST['amount'] : 0;
    $method      = trim($_POST['method'] ?? '');
    $reference   = trim($_POST['reference_no'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

	if (!empty($paymentDate) && $amount > 0) {
	    $stmt = $conn->prepare('
	        INSERT INTO payments
	            (tenant_id, assignment_id, payment_date, amount, method, reference_no, notes)
	        VALUES (?, ?, ?, ?, ?, ?, ?)
	    ');
	    $stmt->bind_param(
	        'iisdsss',
	        $tenantId,
	        $assignmentId,
	        $paymentDate,
	        $amount,
	        $method,
	        $reference,
	        $notes
	    );
	    if ($stmt->execute()) {
	        $paymentId = $stmt->insert_id;
	        $stmt->close();

	        // NEW: auto-allocate to oldest rent then bills
	        allocate_payment($conn, $paymentId, $assignmentId, $amount);

	        $message = 'Payment recorded and allocated.';
	    } else {
	        $stmt->close();
	        $message = 'Error recording payment.';
	    }
	} else {
	    $message = 'Payment date and positive amount are required.';
	}
}

// Fetch payments for this assignment
$stmt = $conn->prepare('
    SELECT id, payment_date, amount, method, reference_no, notes, created_at
    FROM payments
    WHERE assignment_id = ?
    ORDER BY payment_date DESC, id DESC
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$resPay = $stmt->get_result();
$payments = [];
while ($row = $resPay->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();

$pageTitle = 'Payments - ' . $assignment['tenant_name'];
include 'header.php';
?>
<link rel="stylesheet" href="ledger.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Payments</h1>
                    <p class="page-subtitle">
                        <?php echo htmlspecialchars($assignment['tenant_name']); ?> ·
                        <?php echo htmlspecialchars($assignment['building_name'] . ' - ' . $assignment['unit_name']); ?>
                    </p>
                </div>
                <div>
                    <a href="tenant_profile.php?assignment_id=<?php echo (int)$assignmentId; ?>"
                       class="btn btn-outline-small">
                        ← Back to Tenant Profile
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="dashboard-tables">
                <!-- LEFT: Payment history -->
                <div class="dashboard-table">
                    <h4>Payment History</h4>
                    <?php if (empty($payments)): ?>
                        <p class="empty-text">No payments recorded yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount (₹)</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($p['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($p['method']); ?></td>
                                    <td><?php echo htmlspecialchars($p['reference_no']); ?></td>
                                    <td><?php echo htmlspecialchars($p['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Add payment form -->
                <div class="dashboard-table">
                    <h4>Add Payment</h4>
                    <form method="post" class="form-vertical">
                        <div class="form-group">
                            <label for="payment_date">Payment Date</label>
                            <input type="date" id="payment_date" name="payment_date" required
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="amount">Amount (₹)</label>
                            <input type="number" step="0.01" min="0.01" id="amount" name="amount" required>
                        </div>

                        <div class="form-group">
                            <label for="method">Method</label>
                            <select id="method" name="method">
                                <option value="">Select</option>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reference_no">Reference No</label>
                            <input type="text" id="reference_no" name="reference_no"
                                   placeholder="UTR / cheque no / note">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2"
                                      placeholder="Rent + EB split or any details"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="add_payment" class="btn btn-primary">Save Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
