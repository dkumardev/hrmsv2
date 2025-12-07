<?php
require_once 'db.php';
require_once 'auth.php';
require_owner_login();

// ----- MESSAGE -----
$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')   $message = 'Tenant added successfully.';
    if ($_GET['msg'] === 'updated') $message = 'Tenant updated successfully.';
    if ($_GET['msg'] === 'deleted') $message = 'Tenant deleted.';
}

// ----- ADD TENANT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $full_name       = trim($_POST['full_name'] ?? '');
    $father_name     = trim($_POST['father_name'] ?? '');
    $phone_number    = trim($_POST['phone_number'] ?? '');
    $current_address = trim($_POST['current_address'] ?? '');
    $id_proof_type   = trim($_POST['id_proof_type'] ?? '');
    $id_proof_number = trim($_POST['id_proof_number'] ?? '');
    $tenant_status   = $_POST['tenant_status'] ?? 'New';

    $id_proof_file = null;

    if ($full_name !== '' && $father_name !== '' && $phone_number !== '') {
        if (!empty($_FILES['id_proof_file']['name'])) {
            $uploadDir = 'id_proofs/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $ext        = pathinfo($_FILES['id_proof_file']['name'], PATHINFO_EXTENSION);
            $safeName   = preg_replace('/[^a-z0-9]+/i', '_', strtolower($full_name));
            $safePhone  = preg_replace('/\D+/', '', $phone_number);
            $newFileName = $safeName . '_' . $safePhone . '.' . $ext;
            $targetPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['id_proof_file']['tmp_name'], $targetPath)) {
                $id_proof_file = $targetPath;
            }
        }

        $stmt = $conn->prepare(
            'INSERT INTO tenants (full_name, father_name, phone_number, current_address, id_proof_type, id_proof_number, id_proof_file, tenant_status)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'ssssssss',
            $full_name,
            $father_name,
            $phone_number,
            $current_address,
            $id_proof_type,
            $id_proof_number,
            $id_proof_file,
            $tenant_status
        );
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: tenants.php?msg=added');
            exit;
        }
        $stmt->close();
        $message = 'Error adding tenant.';
    } else {
        $message = 'Full name, father name and phone are required.';
    }
}

// ----- UPDATE TENANT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tenant'])) {
    $id              = (int)($_POST['id'] ?? 0);
    $full_name       = trim($_POST['full_name'] ?? '');
    $father_name     = trim($_POST['father_name'] ?? '');
    $phone_number    = trim($_POST['phone_number'] ?? '');
    $current_address = trim($_POST['current_address'] ?? '');
    $id_proof_type   = trim($_POST['id_proof_type'] ?? '');
    $id_proof_number = trim($_POST['id_proof_number'] ?? '');
    $tenant_status   = $_POST['tenant_status'] ?? 'New';

    $id_proof_file   = $_POST['existing_id_proof_file'] ?? null;

    if ($id > 0 && $full_name !== '' && $father_name !== '' && $phone_number !== '') {
        if (!empty($_FILES['id_proof_file']['name'])) {
            $uploadDir = 'id_proofs/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $ext        = pathinfo($_FILES['id_proof_file']['name'], PATHINFO_EXTENSION);
            $safeName   = preg_replace('/[^a-z0-9]+/i', '_', strtolower($full_name));
            $safePhone  = preg_replace('/\D+/', '', $phone_number);
            $newFileName = $safeName . '_' . $safePhone . '.' . $ext;
            $targetPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['id_proof_file']['tmp_name'], $targetPath)) {
                $id_proof_file = $targetPath;
            }
        }

        $stmt = $conn->prepare(
            'UPDATE tenants
             SET full_name=?, father_name=?, phone_number=?, current_address=?, id_proof_type=?, id_proof_number=?, id_proof_file=?, tenant_status=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'ssssssssi',
            $full_name,
            $father_name,
            $phone_number,
            $current_address,
            $id_proof_type,
            $id_proof_number,
            $id_proof_file,
            $tenant_status,
            $id
        );
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: tenants.php?msg=updated');
            exit;
        }
        $stmt->close();
        $message = 'Error updating tenant.';
    } else {
        $message = 'Invalid data for update.';
    }
}

// ----- DELETE TENANT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM tenants WHERE id=?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: tenants.php?msg=deleted');
            exit;
        }
        $stmt->close();
        $message = 'Error deleting tenant (maybe assignments exist).';
    }
}

// ----- FILTERS -----
$filterStatus = trim($_GET['status'] ?? '');
$whereClause  = '';
$params       = [];
$types        = '';

if ($filterStatus !== '') {
    $whereClause = 'WHERE tenant_status = ?';
    $params[]    = $filterStatus;
    $types      .= 's';
}

// ----- FETCH TENANTS -----
$tenants = [];
$sql = 'SELECT * FROM tenants ' . $whereClause . ' ORDER BY id DESC';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

while ($row = $result->fetch_assoc()) {
    $tenants[] = $row;
}
if (isset($stmt) && $stmt) {
    $stmt->close();
}

// ----- EDIT MODE -----
$editTenant = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    foreach ($tenants as $t) {
        if ((int)$t['id'] === $editId) {
            $editTenant = $t;
            break;
        }
    }
}

$pageTitle = 'Tenants - MyProperty Manager';
include 'header.php';
?>
<link rel="stylesheet" href="tenants.css">

<main class="main-content">
    <section class="page-wrapper">
        <div class="container">
            <div class="page-header-row">
                <div>
                    <h1 class="page-title">Tenants</h1>
                    <p class="page-subtitle">Manage tenant profiles and ID proofs.</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-small">← Back to Dashboard</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Status filter (same layout as units filter) -->
            <div class="filters-section">
                <form method="get" class="filters-form" id="tenantFilterForm">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status"
                                onchange="document.getElementById('tenantFilterForm').submit()">
                            <option value="">All Status</option>
                            <option value="New"      <?php echo $filterStatus === 'New'      ? 'selected' : ''; ?>>New</option>
                            <option value="Active"   <?php echo $filterStatus === 'Active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $filterStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="dashboard-grid">
                <!-- LEFT: Tenants list -->
                <div class="section">
                    <div class="section-header-row">
                        <h2 class="section-title">Tenants List (<?php echo count($tenants); ?>)</h2>
                    </div>

                    <?php if (empty($tenants)): ?>
                        <p class="empty-text">
                            No tenants found for this status. Add a new tenant on the right, then assign them to a unit.
                        </p>
                    <?php else: ?>
                        <?php foreach ($tenants as $tenant): ?>
                            <div class="tenant-item">
                                <div class="tenant-main">
                                    <h3 class="tenant-name">
                                        <?php echo htmlspecialchars($tenant['full_name']); ?>
                                        <span class="badge badge-<?php echo strtolower($tenant['tenant_status']); ?>">
                                            <?php echo htmlspecialchars($tenant['tenant_status']); ?>
                                        </span>
                                    </h3>

                                    <p class="tenant-meta">
                                        <?php echo htmlspecialchars($tenant['phone_number']); ?>
                                    </p>

                                    <?php if (!empty($tenant['current_address'])): ?>
                                        <p class="tenant-meta small">
                                            <?php echo htmlspecialchars($tenant['current_address']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($tenant['id_proof_type']) || !empty($tenant['id_proof_number'])): ?>
                                        <p class="tenant-meta small">
                                            ID: <?php echo htmlspecialchars($tenant['id_proof_type']); ?>
                                            <?php if (!empty($tenant['id_proof_number'])): ?>
                                                • <?php echo htmlspecialchars($tenant['id_proof_number']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($tenant['id_proof_file'])): ?>
                                                • <a href="<?php echo htmlspecialchars($tenant['id_proof_file']); ?>" target="_blank">
                                                    View file
                                                  </a>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="tenant-actions">
                                    <div class="tenant-actions-row">
                                        <a href="assign_tenant.php" class="btn btn-primary btn-outline-small">
                                            Assign to unit
                                        </a>
                                        <a href="tenants.php?edit_id=<?php echo (int)$tenant['id']; ?>"
                                           class="btn btn-secondary btn-outline-small">
                                            Edit
                                        </a>
                                        <form method="post" class="inline-form"
                                              onsubmit="return confirm('Delete this tenant? Make sure there are no assignments.');">
                                            <input type="hidden" name="id" value="<?php echo (int)$tenant['id']; ?>">
                                            <button type="submit" name="delete_tenant"
                                                    class="btn btn-danger btn-outline-small">
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
                    <h2 class="section-title">
                        <?php echo $editTenant ? 'Edit Tenant' : 'Add New Tenant'; ?>
                    </h2>

                    <form method="post" enctype="multipart/form-data" class="form-vertical">
                        <?php if ($editTenant): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editTenant['id']; ?>">
                            <input type="hidden" name="existing_id_proof_file"
                                   value="<?php echo htmlspecialchars($editTenant['id_proof_file'] ?? ''); ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="full_name">Full name *</label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?php echo $editTenant ? htmlspecialchars($editTenant['full_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="father_name">Father name *</label>
                            <input type="text" id="father_name" name="father_name" required
                                   value="<?php echo $editTenant ? htmlspecialchars($editTenant['father_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone_number">Phone number *</label>
                            <input type="text" id="phone_number" name="phone_number" required
                                   value="<?php echo $editTenant ? htmlspecialchars($editTenant['phone_number']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="current_address">Current address</label>
                            <textarea id="current_address" name="current_address" rows="2"
                                      placeholder="Street, area, city"><?php
                                echo $editTenant ? htmlspecialchars($editTenant['current_address']) : '';
                            ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="id_proof_type">ID proof type</label>
                            <select id="id_proof_type" name="id_proof_type">
                                <option value="">Select</option>
                                <?php
                                $types = ['Aadhar','PAN','Voter ID','Driving License','Passport','Other'];
                                $currentType = $editTenant ? ($editTenant['id_proof_type'] ?? '') : '';
                                foreach ($types as $t):
                                ?>
                                    <option value="<?php echo $t; ?>"
                                        <?php echo $currentType === $t ? 'selected' : ''; ?>>
                                        <?php echo $t; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="id_proof_number">ID proof number</label>
                            <input type="text" id="id_proof_number" name="id_proof_number"
                                   value="<?php echo $editTenant ? htmlspecialchars($editTenant['id_proof_number']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="id_proof_file">ID proof file</label>
                            <input type="file" id="id_proof_file" name="id_proof_file" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if ($editTenant && !empty($editTenant['id_proof_file'])): ?>
                                <p class="small-note">
                                    Existing file:
                                    <a href="<?php echo htmlspecialchars($editTenant['id_proof_file']); ?>" target="_blank">
                                        View
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="tenant_status">Status *</label>
                            <select id="tenant_status" name="tenant_status" required>
                                <?php
                                $curStatus = $editTenant ? $editTenant['tenant_status'] : 'New';
                                ?>
                                <option value="New"      <?php echo $curStatus === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="Active"   <?php echo $curStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $curStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <?php if ($editTenant): ?>
                                <button type="submit" name="update_tenant" class="btn btn-primary">
                                    Save changes
                                </button>
                                <a href="tenants.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_tenant" class="btn btn-primary">
                                    Add tenant
                                </button>
                            <?php endif; ?>
                        </div>

                        <p class="hint-text">
                            After adding a tenant, go to the <a href="units.php">Units</a> page and click
                            “Assign tenant” to link them to a unit.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
