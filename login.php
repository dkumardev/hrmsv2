<?php
require_once 'db.php';

// If already logged in, go to dashboard
if (!empty($_SESSION['owner_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT id, username, password, full_name FROM owners WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($owner_id, $db_username, $db_password, $full_name);

        if ($stmt->fetch() && $password === $db_password) {
            $_SESSION['owner_id']       = $owner_id;
            $_SESSION['owner_username'] = $db_username;
            $_SESSION['owner_name']     = $full_name;
            $stmt->close();
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
        $stmt->close();
    } else {
        $error = 'Username and password are required';
    }
}

$pageTitle = 'Owner Login - MyProperty Manager';
include 'header.php';
?>
<link rel="stylesheet" href="login.css">

<main class="main-content">
    <div class="container">
        <div class="login-wrapper">
            <h1 class="login-title">Owner Login</h1>

            <?php if ($error): ?>
                <p class="login-error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post" class="login-form">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" required>

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>

                <button type="submit" class="btn btn-primary btn-large" style="width:100%;margin-top:1rem;">
                    Login
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
