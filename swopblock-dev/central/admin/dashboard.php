<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/dbconnect.php';
require_once dirname(__DIR__) . '/includes/install_verify.php';

require_admin();

$verifyResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $leftId = (int)($_POST['left_install_id'] ?? 0);
    $rightId = (int)($_POST['right_install_id'] ?? 0);

    if ($leftId > 0 && $rightId > 0 && $leftId !== $rightId) {
        $left = find_installation_by_id($leftId);
        $right = find_installation_by_id($rightId);
        if ($left && $right) {
            $verifyResult = compare_installations($left, $right);
        } else {
            $verifyResult = ['verified' => false, 'message' => 'One or both installations were not found.'];
        }
    } else {
        $verifyResult = ['verified' => false, 'message' => 'Choose two different installation IDs.'];
    }
}

$stmt = db()->query('
    SELECT i.*, c.email AS clientEmail
    FROM Installations i
    JOIN Clients c ON c.clientID = i.clientID
    ORDER BY i.installID DESC
');
$installations = $stmt->fetchAll();

$logStmt = db()->query('
    SELECT l.*, i.installLabel
    FROM API_Access_Logs l
    JOIN Installations i ON i.installID = l.installID
    ORDER BY l.logID DESC
    LIMIT 20
');
$logs = $logStmt->fetchAll();

function mask_key(string $key): string
{
    if (strlen($key) <= 12) {
        return $key;
    }
    return substr($key, 0, 8) . '…' . substr($key, -4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Node — Dashboard</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="admin-header">
    <div>
        <strong>Central Node</strong>
        <span class="muted">Network registry</span>
    </div>
    <div class="admin-user">
        <?php echo htmlspecialchars(admin_email()); ?>
        <a href="logout.php">Log out</a>
    </div>
</header>

<main class="admin-main">
    <section class="panel">
        <h2>Installations</h2>
        <p class="sub">Every client node that registered with this central server.</p>

        <?php if (!$installations): ?>
            <p class="empty">No nodes registered yet. When a client runs the installer, a row appears here.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th>Status</th>
                    <th>API key</th>
                    <th>Version</th>
                    <th>Downloaded</th>
                    <th>Activated</th>
                    <th>Last check-in</th>
                    <th>Last IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installations as $row): ?>
                <tr>
                    <td><?php echo (int)$row['installID']; ?></td>
                    <td><?php echo htmlspecialchars($row['installLabel']); ?></td>
                    <td><span class="status <?php echo htmlspecialchars($row['installStatus']); ?>"><?php echo htmlspecialchars($row['installStatus']); ?></span></td>
                    <td><code><?php echo htmlspecialchars(mask_key((string)$row['apiKey'])); ?></code></td>
                    <td><?php echo htmlspecialchars((string)$row['packageVersion']); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['downloadedAt'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['activatedAt'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['lastCheckinAt'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['lastIPAddress'] ?? '—')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Verify installation</h2>
        <p class="sub">Compare two nodes to confirm they use the same package version and fingerprint.</p>

        <form method="post" class="verify-form">
            <input type="hidden" name="action" value="verify">
            <label>
                Installation A
                <select name="left_install_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($installations as $row): ?>
                    <option value="<?php echo (int)$row['installID']; ?>">
                        #<?php echo (int)$row['installID']; ?> — <?php echo htmlspecialchars($row['installLabel']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Installation B
                <select name="right_install_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($installations as $row): ?>
                    <option value="<?php echo (int)$row['installID']; ?>">
                        #<?php echo (int)$row['installID']; ?> — <?php echo htmlspecialchars($row['installLabel']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Verify match</button>
        </form>

        <?php if ($verifyResult): ?>
        <div class="verify-result <?php echo $verifyResult['verified'] ? 'ok' : 'bad'; ?>">
            <strong><?php echo $verifyResult['verified'] ? 'Verified' : 'Not verified'; ?></strong>
            <p><?php echo htmlspecialchars((string)$verifyResult['message']); ?></p>
            <?php if (!empty($verifyResult['left'])): ?>
            <p>Version match: <?php echo !empty($verifyResult['samePackageVersion']) ? 'yes' : 'no'; ?></p>
            <p>Fingerprint match: <?php echo !empty($verifyResult['sameFingerprint']) ? 'yes' : 'no'; ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Recent API activity</h2>
        <?php if (!$logs): ?>
            <p class="empty">No API calls logged yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Node</th>
                    <th>Endpoint</th>
                    <th>Method</th>
                    <th>Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$log['requestedAt']); ?></td>
                    <td><?php echo htmlspecialchars((string)$log['installLabel']); ?></td>
                    <td><code><?php echo htmlspecialchars((string)$log['endpoint']); ?></code></td>
                    <td><?php echo htmlspecialchars((string)$log['httpMethod']); ?></td>
                    <td><?php echo (int)$log['responseCode']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
