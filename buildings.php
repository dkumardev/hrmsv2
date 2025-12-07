<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

// ----- MESSAGE -----
$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $message = 'Building added successfully.';
    if ($_GET['msg'] === 'updated') $message = 'Building updated successfully.';
    if ($_GET['msg'] === 'deleted') $message = 'Building deleted successfully.';
}

// ----- ADD building -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_building'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare('INSERT INTO buildings (name, address) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $address);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: buildings.php?msg=added');
            exit;
        }
        $stmt->close();
        $message = 'Error adding building.';
    } else {
        $message = 'Building name is required.';
    }
}

// ----- UPDATE building -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_building'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($id > 0 && $name !== '') {
        $stmt = $conn->prepare('UPDATE buildings SET name = ?, address = ? WHERE id = ?');
        $stmt->bind_param('ssi', $name, $address, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: buildings.php?msg=updated');
            exit;
        }
        $stmt->close();
        $message = 'Error updating building.';
    } else {
        $message = 'Invalid data for update.';
    }
}

// ----- DELETE building -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_building'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM buildings WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: buildings.php?msg=deleted');
            exit;
        }
        $stmt->close();
        $message = 'Error deleting building.';
    }
}

// ----- FILTERS -----
$filterName = trim($_GET['name'] ?? '');

// Fetch all building names for dropdown
$allBuildingNames = [];
$nameResult = $conn->query('SELECT DISTINCT name FROM buildings ORDER BY name ASC');
while ($nr = $nameResult->fetch_assoc()) {
    if (!empty($nr['name'])) {
        $allBuildingNames[] = $nr['name'];
    }
}

// FETCH buildings with optional name filter
$buildings = [];
if ($filterName !== '') {
    $stmt = $conn->prepare('SELECT id, name, address, created_at FROM buildings WHERE name = ? ORDER BY id DESC');
    $stmt->bind_param('s', $filterName);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query('SELECT id, name, address, created_at FROM buildings ORDER BY id DESC');
}
while ($row = $result->fetch_assoc()) {
    $buildings[] = $row;
}
if (isset($stmt)) $stmt->close();

// ----- determine mode and data for form -----
$editBuilding = null;
$mode = 'add';
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    foreach ($buildings as $b) {
        if ((int)$b['id'] === $editId) {
            $editBuilding = $b;
            $mode = 'edit';
            break;
        }
    }
}

$pageTitle = 'Buildings - MyProperty Manager';
include 'header.php';
?>
<link rel="stylesheet" href="buildings.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Buildings</h1>
                    <p class="page-subtitle">Add and manage all your buildings here.</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-small">← Back to Dashboard</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Filters - TENANT STYLE: Single filter-group with auto-submit -->
            <div class="filters-section">
                <form method="get" class="filters-form" id="buildingFilterForm">
                    <div class="filter-group">
                        <label for="status">Building</label>
                        <select id="status" name="name" onchange="document.getElementById('buildingFilterForm').submit()">
                            <option value="">All buildings</option>
                            <?php foreach ($allBuildingNames as $bName): ?>
                                <option value="<?php echo htmlspecialchars($bName); ?>" <?php echo $filterName === $bName ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="dashboard-grid">
                <!-- LEFT: Buildings list -->
                <div class="section">
                    <h2 class="section-title">Buildings list</h2>
                    <?php if (empty($buildings)): ?>
                        <p class="empty-text">No buildings yet. Add your first building using the form.</p>
                    <?php else: ?>
                        <?php foreach ($buildings as $b): ?>
                            <div class="building-item">
                                <div class="building-main">
                                    <h3 class="building-name"><?php echo htmlspecialchars($b['name']); ?></h3>
                                    <p class="building-address">
                                        <?php echo !empty($b['address']) ? htmlspecialchars($b['address']) : 'No address'; ?>
                                    </p>
                                    <!--<p class="building-meta">
                                        ID: <?php echo (int)$b['id']; ?> • Created: <?php echo htmlspecialchars($b['created_at']); ?>
                                    </p> -->
                                </div>
                                <div class="building-actions">
                                    <div class="building-actions-row">
                                        <a href="units.php?building_id=<?php echo (int)$b['id']; ?>" class="btn btn-secondary btn-outline-small">Manage units</a>
                                        <a href="buildings.php?edit_id=<?php echo (int)$b['id']; ?>" class="btn btn-primary btn-outline-small">Edit</a>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this building? All related units will be lost.');">
                                            <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                            <button type="submit" name="delete_building" class="btn btn-danger btn-outline-small">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Add / Edit building form -->
                <div class="section">
                    <h2 class="section-title"><?php echo $mode === 'edit' ? 'Edit building' : 'Add new building'; ?></h2>
                    <form method="post" class="form-vertical">
                        <?php if ($mode === 'edit' && $editBuilding): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editBuilding['id']; ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="name">Building name</label>
                            <input type="text" id="name" name="name" required maxlength="100" placeholder="Building name"
                                   value="<?php echo $mode === 'edit' && $editBuilding ? htmlspecialchars($editBuilding['name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3" maxlength="500" placeholder="Building address">
                                <?php echo $mode === 'edit' && $editBuilding ? htmlspecialchars($editBuilding['address']) : ''; ?>
                            </textarea>
                        </div>
                        <div class="form-actions">
                            <?php if ($mode === 'edit' && $editBuilding): ?>
                                <button type="submit" name="update_building" class="btn btn-primary">Save changes</button>
                                <a href="buildings.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_building" class="btn btn-primary">Add building</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
