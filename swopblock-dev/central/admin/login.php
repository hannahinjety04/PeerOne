<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter email and password.';
    } elseif (login_admin($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid owner credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Node — Owner Login</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<main class="admin-card">
    <h1>Central node login</h1>
    <p class="sub">Owner / admin access only. Client nodes do not log in here.</p>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>
            Email
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Log in</button>
    </form>

    <p class="hint">Default after importing schema: <code>owner@central.local</code> / <code>changeme123</code></p>
    <p class="back"><a href="../index.php">&larr; Public site</a></p>
</main>
</body>
</html>
