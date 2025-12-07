<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

// Fetch buildings for dropdown
$buildingsResult = $conn->query("SELECT id, name FROM buildings ORDER BY name");
$buildings = [];
while ($row = $buildingsResult->fetch_assoc()) {
    $buildings[] = $row;
}

// Check if any tenants exist
$tenantsCountResult = $conn->query("SELECT COUNT(*) as c FROM tenants");
$tenantsCount = $tenantsCountResult->fetch_assoc()['c'];

// Handle filters
$filterBuildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
$filterFloor      = trim($_GET['floor'] ?? '');
$filterType       = trim($_GET['type'] ?? '');
$filterStatus     = trim($_GET['status'] ?? '');

// Build WHERE clause
$whereConditions = [];
$params = [];
$types = '';

if ($filterBuildingId > 0) {
    $whereConditions[] = 'u.building_id = ?';
    $params[] = $filterBuildingId;
    $types   .= 'i';
}
if ($filterFloor !== '') {
    $whereConditions[] = 'u.floor = ?';
    $params[] = $filterFloor;
    $types   .= 's';
}
if ($filterType !== '') {
    $whereConditions[] = 'u.unit_type = ?';
    $params[] = $filterType;
    $types   .= 's';
}
if ($filterStatus !== '') {
    $whereConditions[] = 'u.status = ?';
    $params[] = $filterStatus;
    $types   .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// ----- SINGLE QUERY: Units + Tenant Info + Current Assignment -----
$sql = "
    SELECT 
        u.*,
        b.name AS building_name,
        ua.id AS current_assignment_id,
        t.full_name,
        t.phone_number
    FROM units u 
    LEFT JOIN buildings b       ON u.building_id = b.id
    LEFT JOIN unit_assignments ua 
           ON u.id = ua.unit_id 
          AND ua.end_date IS NULL
    LEFT JOIN tenants t         ON ua.tenant_id = t.id
    $whereClause 
    ORDER BY u.building_id, u.floor, u.id
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

// Handle flash message
$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')    $message = 'Unit added successfully.';
    if ($_GET['msg'] === 'updated')  $message = 'Unit updated successfully.';
    if ($_GET['msg'] === 'deleted')  $message = 'Unit deleted successfully.';
    if ($_GET['msg'] === 'assigned') $message = 'Tenant assigned successfully.';
}

// ADD unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $buildingId = (int)($_POST['building_id'] ?? 0);
    $unitName   = trim($_POST['unit_name'] ?? '');
    $floor      = trim($_POST['floor'] ?? '');
    $unitType   = $_POST['unit_type'] ?? '';
    $amenities  = trim($_POST['amenities'] ?? '');
    $status     = $_POST['status'] ?? '';

    if ($buildingId > 0 && $unitName && $unitType && $status) {
        $stmt = $conn->prepare("
            INSERT INTO units (building_id, unit_name, floor, unit_type, amenities, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssss', $buildingId, $unitName, $floor, $unitType, $amenities, $status);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: units.php?msg=added');
            exit;
        }
        $stmt->close();
        $message = 'Error adding unit.';
    } else {
        $message = 'Building, unit name, type and status are required.';
    }
}

// UPDATE unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_unit'])) {
    $id         = (int)($_POST['id'] ?? 0);
    $buildingId = (int)($_POST['building_id'] ?? 0);
    $unitName   = trim($_POST['unit_name'] ?? '');
    $floor      = trim($_POST['floor'] ?? '');
    $unitType   = $_POST['unit_type'] ?? '';
    $amenities  = trim($_POST['amenities'] ?? '');
    $status     = $_POST['status'] ?? '';

    if ($id > 0 && $buildingId > 0 && $unitName) {
        $stmt = $conn->prepare("
            UPDATE units 
               SET building_id=?, unit_name=?, floor=?, unit_type=?, amenities=?, status=? 
             WHERE id=?
        ");
        $stmt->bind_param('isssssi', $buildingId, $unitName, $floor, $unitType, $amenities, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: units.php?msg=updated');
            exit;
        }
        $stmt->close();
        $message = 'Error updating unit.';
    } else {
        $message = 'Invalid data for update.';
    }
}

// DELETE unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_unit'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM units WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: units.php?msg=deleted');
            exit;
        }
        $stmt->close();
        $message = 'Error deleting unit.';
    }
}

// Edit mode
$editUnit = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    foreach ($units as $u) {
        if ((int)$u['id'] === $editId) {
            $editUnit = $u;
            break;
        }
    }
}

$pageTitle = 'Units - MyProperty Manager';
include 'header.php';
?>
<link rel="stylesheet" href="units.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Units</h1>
                    <p class="page-subtitle">Rooms and shops under your buildings.</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-small">← Back to Dashboard</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-section">
                <form method="get" class="filters-form">
                    <div class="filter-group">
                        <label>Building</label>
                        <select name="building_id">
                            <option value="">All buildings</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>" <?php echo $filterBuildingId === (int)$b['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Floor</label>
                        <select name="floor">
                            <option value="">All floors</option>
                            <option value="0" <?php echo $filterFloor === '0' ? 'selected' : ''; ?>>Ground</option>
                            <option value="1" <?php echo $filterFloor === '1' ? 'selected' : ''; ?>>1st</option>
                            <option value="2" <?php echo $filterFloor === '2' ? 'selected' : ''; ?>>2nd</option>
                            <option value="3" <?php echo $filterFloor === '3' ? 'selected' : ''; ?>>3rd</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Vacant"   <?php echo $filterStatus === 'Vacant' ? 'selected' : ''; ?>>Vacant</option>
                            <option value="Occupied" <?php echo $filterStatus === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <option value="Room" <?php echo $filterType === 'Room' ? 'selected' : ''; ?>>Room</option>
                            <option value="Shop" <?php echo $filterType === 'Shop' ? 'selected' : ''; ?>>Shop</option>
                            <option value="Flat" <?php echo $filterType === 'Flat' ? 'selected' : ''; ?>>Flat</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-small">Filter</button>
                    <a href="units.php" class="btn btn-secondary btn-small">Clear</a>
                </form>
            </div>

            <?php if ($tenantsCount == 0): ?>
                <div class="alert alert-warning">
                    <strong>No tenants yet.</strong> Create tenants first <a href="tenants.php">Go to Tenants</a>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- LEFT: Units list -->
                <div class="section">
                    <h2 class="section-title">Units List (<?php echo count($units); ?>)</h2>
                    <?php if (empty($units)): ?>
                        <p class="empty-text">No units match your filters. Adjust filters or add a unit.</p>
                    <?php else: ?>
                        <?php foreach ($units as $unit): ?>
                            <div class="unit-item">
                                <div class="unit-main">
                                    <?php if (!empty($unit['amenities'])): ?>
                                        <!-- amenities pill (optional) -->
                                    <?php endif; ?>

                                    <h3 class="unit-name">
                                        <?php echo htmlspecialchars($unit['building_name'] . ' - ' . $unit['unit_name']); ?>
                                        <span class="unit-type-pill"><?php echo htmlspecialchars($unit['unit_type']); ?></span>
                                    </h3>

                                    <?php if (!empty($unit['floor'])): ?>
                                        <p class="unit-building-floor">Floor <?php echo htmlspecialchars($unit['floor']); ?></p>
                                    <?php endif; ?>

                                    <p class="unit-status-line">
                                        Status
                                        <span class="status-text status-text-<?php echo strtolower($unit['status']); ?>">
                                            <?php echo htmlspecialchars($unit['status']); ?>
                                        </span>
                                    </p>

                                    <?php if ($unit['status'] === 'Occupied' && $unit['full_name']): ?>
                                        <p>
                                            <strong>
                                                <a href="tenant_profile.php?assignment_id=<?php echo (int)$unit['current_assignment_id']; ?>">
                                                    <?php echo htmlspecialchars($unit['full_name']); ?>
                                                </a>
                                            </strong><br>
                                            <!-- <small><?php echo htmlspecialchars($unit['phone_number']); ?></small> -->
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="unit-actions">
                                    <div class="unit-actions-row">
                                        <?php if ($tenantsCount > 0): ?>
                                            <a href="assign_tenant.php?unit_id=<?php echo (int)$unit['id']; ?>"
                                               class="btn btn-primary btn-outline-small">
                                                <?php echo $unit['status'] === 'Vacant' ? 'Assign tenant' : 'Change tenant'; ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="units.php?edit_id=<?php echo (int)$unit['id']; ?>"
                                           class="btn btn-secondary btn-outline-small">Edit</a>
                                        <form method="post" class="inline-form"
                                              onsubmit="return confirm('Delete this unit? All related assignments will be lost.');">
                                            <input type="hidden" name="id" value="<?php echo (int)$unit['id']; ?>">
                                            <button type="submit" name="delete_unit" class="btn btn-danger btn-outline-small">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Add/Edit form -->
                <div class="section">
                    <h2 class="section-title"><?php echo $editUnit ? 'Edit Unit' : 'Add New Unit'; ?></h2>
                    <form method="post" class="form-vertical">
                        <?php if ($editUnit): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editUnit['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="building_id">Building</label>
                            <select id="building_id" name="building_id" required>
                                <option value="">Select building</option>
                                <?php foreach ($buildings as $b): ?>
                                    <option value="<?php echo (int)$b['id']; ?>"
                                        <?php echo ($editUnit && $editUnit['building_id'] == $b['id']) || ($filterBuildingId == $b['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="unit_name">Unit name</label>
                            <input type="text" id="unit_name" name="unit_name" required maxlength="100"
                                   value="<?php echo $editUnit ? htmlspecialchars($editUnit['unit_name']) : ''; ?>"
                                   placeholder="e.g. Room 101, Shop 2">
                        </div>

                        <div class="form-group">
                            <label for="floor">Floor</label>
                            <input type="text" id="floor" name="floor" maxlength="50"
                                   value="<?php echo $editUnit ? htmlspecialchars($editUnit['floor']) : ''; ?>"
                                   placeholder="e.g. Ground, 1st">
                        </div>

                        <div class="form-group">
                            <label for="unit_type">Type</label>
                            <select id="unit_type" name="unit_type" required>
                                <option value="Room" <?php echo ($editUnit && $editUnit['unit_type'] === 'Room') ? 'selected' : ''; ?>>Room</option>
                                <option value="Shop" <?php echo ($editUnit && $editUnit['unit_type'] === 'Shop') ? 'selected' : ''; ?>>Shop</option>
                                <option value="Flat" <?php echo ($editUnit && $editUnit['unit_type'] === 'Flat') ? 'selected' : ''; ?>>Flat</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="amenities">Amenities</label>
                            <input type="text" id="amenities" name="amenities" maxlength="255"
                                   value="<?php echo $editUnit ? htmlspecialchars($editUnit['amenities']) : ''; ?>"
                                   placeholder="Fan, attached bathroom, balcony, shutter etc.">
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="Vacant"   <?php echo ($editUnit && $editUnit['status'] === 'Vacant')   ? 'selected' : ''; ?>>Vacant</option>
                                <option value="Occupied" <?php echo ($editUnit && $editUnit['status'] === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <?php if ($editUnit): ?>
                                <button type="submit" name="update_unit" class="btn btn-primary">Save changes</button>
                                <a href="units.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_unit" class="btn btn-primary">Add unit</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
