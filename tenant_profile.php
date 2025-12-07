<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if ($assignmentId <= 0) {
    header('Location: units.php');
    exit;
}

/**
 * Ensure rent_items exist for this assignment up to target date.
 * Target = today + 1 month or assignment end_date, whichever is earlier.
 */
function ensure_rent_items_up_to_target(mysqli $conn, int $assignmentId): void
{
    // 1) Fetch assignment details
    $stmt = $conn->prepare('
        SELECT start_date, end_date, monthly_rent
        FROM unit_assignments
        WHERE id = ?
    ');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $row  = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }

    $startDate   = $row['start_date'];
    $endDateDb   = $row['end_date'];
    $monthlyRent = (float)$row['monthly_rent'];

    if (empty($startDate) || $monthlyRent <= 0) {
        return;
    }

    // 2) Target date = min(assignment_end_date or open‑ended, today + 1 month)
    $today      = new DateTimeImmutable('today');
    $targetDate = $today->modify('+1 month');

    if (!empty($endDateDb)) {
        $assignmentEnd = new DateTimeImmutable($endDateDb);
        if ($assignmentEnd < $targetDate) {
            $targetDate = $assignmentEnd;
        }
    }

    // 3) Find last existing month_end for this assignment
    $stmt = $conn->prepare('
        SELECT month_start, month_end
        FROM rent_items
        WHERE assignment_id = ?
        ORDER BY month_end DESC
        LIMIT 1
    ');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $resLast = $stmt->get_result();
    $lastRow = $resLast->fetch_assoc();
    $stmt->close();

    if ($lastRow) {
        $currentStart = new DateTimeImmutable($lastRow['month_end']);
    } else {
        // No rows yet → first period starts at assignment start_date
        $currentStart = new DateTimeImmutable($startDate);
    }

    // 4) Insert new periods until month_end >= targetDate
    $stmtInsert = $conn->prepare('
        INSERT INTO rent_items (assignment_id, month_start, month_end, amount)
        VALUES (?, ?, ?, ?)
    ');

    while (true) {
        $nextStart = $currentStart;
        $nextEnd   = $nextStart->modify('+1 month');

        if ($nextEnd > $targetDate) {
            break;
        }

        $ms = $nextStart->format('Y-m-d');
        $me = $nextEnd->format('Y-m-d');

        $stmtInsert->bind_param('issd', $assignmentId, $ms, $me, $monthlyRent);
        $stmtInsert->execute();

        $currentStart = $nextEnd;
    }

    $stmtInsert->close();
}

// --- Generate missing rent_items for this assignment ---
ensure_rent_items_up_to_target($conn, $assignmentId);

// --- Fetch assignment + tenant + unit info ---
$stmt = $conn->prepare('
    SELECT ua.*, 
           u.unit_name, u.floor, u.unit_type,
           b.name AS building_name,
           t.full_name AS tenant_name, t.phone_number
    FROM unit_assignments ua
    JOIN units u      ON ua.unit_id = u.id
    JOIN buildings b  ON ua.building_id = b.id
    JOIN tenants t    ON ua.tenant_id = t.id
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

// --- Fetch all rent_items (after generation) ---
$stmt = $conn->prepare('
    SELECT id, month_start, month_end, amount
    FROM rent_items
    WHERE assignment_id = ?
    ORDER BY month_start ASC
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$resItems = $stmt->get_result();
$rentItems = [];
while ($row = $resItems->fetch_assoc()) {
    $rentItems[] = $row;
}
$stmt->close();

// --- Totals: Rent scheduled / paid ---
$stmt = $conn->prepare('
    SELECT 
        COALESCE(SUM(amount), 0) AS scheduled_rent
    FROM rent_items
    WHERE assignment_id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$scheduledRent = (float)$res['scheduled_rent'];
$stmt->close();

$stmt = $conn->prepare('
    SELECT 
        COALESCE(SUM(pa.amount), 0) AS rent_paid
    FROM payment_allocations pa
    JOIN rent_items ri ON pa.item_type = "RENT" AND pa.item_id = ri.id
    WHERE ri.assignment_id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$rentPaid = (float)$res['rent_paid'];
$stmt->close();

$rentDue = $scheduledRent - $rentPaid;

// --- Totals: Bills scheduled / paid ---
$stmt = $conn->prepare('
    SELECT 
        COALESCE(SUM(amount), 0) AS scheduled_bills
    FROM bills
    WHERE assignment_id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$scheduledBills = (float)$res['scheduled_bills'];
$stmt->close();

$stmt = $conn->prepare('
    SELECT 
        COALESCE(SUM(pa.amount), 0) AS bills_paid
    FROM payment_allocations pa
    JOIN bills b ON pa.item_type = "BILL" AND pa.item_id = b.id
    WHERE b.assignment_id = ?
');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$billsPaid = (float)$res['bills_paid'];
$stmt->close();

$billsDue = $scheduledBills - $billsPaid;

// --- Grand totals ---
$totalScheduled = $scheduledRent + $scheduledBills;
$totalPaid      = $rentPaid + $billsPaid;
$totalDue       = $totalScheduled - $totalPaid;


$pageTitle = 'Tenant Profile - ' . $assignment['tenant_name'];
include 'header.php';
?>
<link rel="stylesheet" href="assign_tenant.css">
<link rel="stylesheet" href="ledger.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Tenant Profile</h1>
                    <p class="page-subtitle">
                        <?php echo htmlspecialchars($assignment['tenant_name']); ?> ·
                        <?php echo htmlspecialchars($assignment['phone_number']); ?> ·
                        <?php echo htmlspecialchars($assignment['building_name'] . ' - ' . $assignment['unit_name']); ?>
                    </p>
                </div>
                <div>
                    <a href="units.php" class="btn btn-outline-small">← Back to Units</a>
					<div>
					    <a href="bills.php?assignment_id=<?php echo (int)$assignmentId; ?>" 
					       class="btn btn-outline-small">
					        Bills
					    </a>
						<a href="payments.php?assignment_id=<?php echo (int)$assignmentId; ?>" 
						       class="btn btn-outline-small">
						        Payments
						    </a>
						    <a href="bills.php?assignment_id=<?php echo (int)$assignmentId; ?>" 
						       class="btn btn-outline-small">
						        Bills
						    </a>
						    <a href="units.php" class="btn btn-outline-small">← Back to Units</a>
					</div>
                </div>
            </div>
			
			<div class="stats-grid" style="margin-top:1rem;margin-bottom:1.5rem;">
			    <div class="stat-card">
			        <div class="stat-label">Total Rent</div>
			        <div class="stat-value">₹<?php echo number_format($scheduledRent, 2); ?></div>
			        <div class="stat-label">Paid: ₹<?php echo number_format($rentPaid, 2); ?> · Due: ₹<?php echo number_format($rentDue, 2); ?></div>
			    </div>
			    <div class="stat-card">
			        <div class="stat-label">Total Bills</div>
			        <div class="stat-value">₹<?php echo number_format($scheduledBills, 2); ?></div>
			        <div class="stat-label">Paid: ₹<?php echo number_format($billsPaid, 2); ?> · Due: ₹<?php echo number_format($billsDue, 2); ?></div>
			    </div>
			    <div class="stat-card highlight">
			        <div class="stat-label">Grand Total Due</div>
			        <div class="stat-value">₹<?php echo number_format($totalDue, 2); ?></div>
			        <div class="stat-label">Total Paid: ₹<?php echo number_format($totalPaid, 2); ?></div>
			    </div>
			</div>


            <div class="dashboard-grid">
                <!-- LEFT: Rent schedule -->
                <div class="section">
                    <h2 class="section-title">Rent Schedule</h2>

                    <?php if (empty($rentItems)): ?>
                        <p class="empty-text">No rent periods generated yet.</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Amount (₹)</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rentItems as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($item['month_start']); ?></td>
                                        <td><?php echo htmlspecialchars($item['month_end']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($item['amount'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Assignment details -->
                <div class="section">
                    <h2 class="section-title">Assignment Details</h2>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <tbody>
                            <tr>
                                <th>Building</th>
                                <td><?php echo htmlspecialchars($assignment['building_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Unit</th>
                                <td>
                                    <?php echo htmlspecialchars($assignment['unit_name']); ?>
                                    (<?php echo htmlspecialchars($assignment['unit_type']); ?>)
                                </td>
                            </tr>
                            <tr>
                                <th>Floor</th>
                                <td><?php echo htmlspecialchars($assignment['floor']); ?></td>
                            </tr>
                            <tr>
                                <th>Tenant</th>
                                <td><?php echo htmlspecialchars($assignment['tenant_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($assignment['phone_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Start date</th>
                                <td><?php echo htmlspecialchars($assignment['start_date']); ?></td>
                            </tr>
                            <tr>
                                <th>End date / Status</th>
                                <td>
                                    <?php
                                    if (!empty($assignment['end_date'])) {
                                        echo 'Ended on ' . htmlspecialchars($assignment['end_date']);
                                    } else {
                                        echo 'Active';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Monthly rent</th>
                                <td>₹<?php echo htmlspecialchars(number_format($assignment['monthly_rent'], 2)); ?></td>
                            </tr>
                            <tr>
                                <th>Advance deposit</th>
                                <td>₹<?php echo htmlspecialchars(number_format($assignment['advance_deposit'], 2)); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
