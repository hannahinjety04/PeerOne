<?php
// =============================================================================
// PHP SETUP — runs on the server before any HTML is sent to the browser
// =============================================================================

// Current year for the footer (e.g. 2026). (int) converts it to a plain number.
$year = (int)date('Y');

// Should we auto-start the zip download when this page loads?
// Normal visit (install.php)           → $autoDownload is true
// Test visit (install.php?skip)        → $autoDownload is false (no download)
// $_GET holds values from the URL after "?", like ?skip
$autoDownload = !isset($_GET['skip']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard page settings -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Server</title>

    <!-- Shared styles (same file used by index.php) -->
    <link rel="stylesheet" href="server.css">
</head>
<body>

<main class="install-progress">
    <h1>Your download is starting</h1>
    <p class="lead">Install your Swopblock node on this device, then connect it to the marketplace network.</p>

    <div class="step-box">
        <h2>What happens next</h2>
        <p>1. Open the downloaded package.</p>
        <p>2. Run the installer.</p>
        <p>3. Your node will be created on this device and registered with the central network.</p>
        <p>4. Open your local node dashboard and begin using the marketplace.</p>
    </div>

    <div class="step-box">
        <h2>Need the download again?</h2>
        <p><a href="download.php" style="color:#4f8cff;">Download the node package</a></p>
    </div>

    <div class="step-box">
        <h2>Manual setup</h2>
        <p>If your system asks for manual steps, use the files inside the package:</p>
        <p><strong>Windows:</strong> open <code style="display:inline;padding:2px 6px;">install.bat</code></p>
        <p><strong>Linux:</strong> run <code style="display:inline;padding:2px 6px;">pwsh ./install.ps1</code></p>
        <p style="margin-top:10px;">After install, start the local node and open <code style="display:inline;padding:2px 6px;">http://localhost:8080</code>.</p>
    </div>

    <a href="index.php" class="back-home">&larr; Back to home</a>
</main>

<footer>
    <!-- PHP echoes the $year variable into the HTML -->
    <p>&copy; <?php echo $year; ?> Crypto Marketplace</p>
</footer>

<?php
// Only load install.js when auto-download is enabled.
// install.js creates a hidden iframe that loads download.php (starts the zip download).
// Visit install.php?skip to load this page WITHOUT triggering a download.
if ($autoDownload):
?>
<script src="install.js"></script>
<?php endif; ?>

</body>
</html>
