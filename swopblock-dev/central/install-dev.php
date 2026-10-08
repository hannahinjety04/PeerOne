<?php
$year = (int)date('Y');
$autoDownload = !isset($_GET['skip']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Development Tools</title>
    <link rel="stylesheet" href="server.css">
</head>
<body>

<main class="install-progress">
    <h1>Development tools download</h1>
    <p class="lead">Install a local central node and client node on this machine using SQLite — no XAMPP or MySQL required.</p>

    <div class="step-box">
        <h2>What you get</h2>
        <p>1. Local central marketplace on <code style="display:inline;padding:2px 6px;">http://localhost:8081</code></p>
        <p>2. Local client node on <code style="display:inline;padding:2px 6px;">http://localhost:8080</code></p>
        <p>3. Portable PHP (downloaded only if needed, with SHA-256 verification)</p>
        <p>4. Admin login seeded for local testing</p>
    </div>

    <div class="step-box">
        <h2>What happens next</h2>
        <p>1. Open the downloaded zip.</p>
        <p>2. Double-click <code style="display:inline;padding:2px 6px;">install-dev.bat</code></p>
        <p>3. Start central, then start client, from <code style="display:inline;padding:2px 6px;">%USERPROFILE%\SwopblockDev</code></p>
    </div>

    <div class="step-box">
        <h2>Need the download again?</h2>
        <p><a href="download-dev.php" style="color:#4f8cff;">Download development tools</a></p>
    </div>

    <a href="index.php" class="back-home">&larr; Back to home</a>
</main>

<footer>
    <p>&copy; <?php echo $year; ?> Crypto Marketplace</p>
</footer>

<?php if ($autoDownload): ?>
<script src="install-dev.js"></script>
<?php endif; ?>

</body>
</html>
