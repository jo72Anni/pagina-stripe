<?php
$pdo = require __DIR__ . '/db.php';

// Funzione per ottenere tutte le tabelle
function getAllTables(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$tables = getAllTables($pdo);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Sistema</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 50%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h1>Informazioni Sistema</h1>
    <ul>
        <li><strong>PHP Version:</strong> <?= phpversion() ?></li>
        <li><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></li>
        <li><strong>Environment:</strong> <?= getenv('APP_ENV') ?: 'N/A' ?></li>
        <li><strong>Debug Mode:</strong> <?= getenv('APP_DEBUG') ?: 'N/A' ?></li>
    </ul>

    <h2>Database PostgreSQL</h2>
    <p>Connesso a: <?= htmlspecialchars(getenv('DB_NAME')) ?> (Host: <?= htmlspecialchars(getenv('DB_HOST')) ?>)</p>

    <h2>Tabelle presenti</h2>
    <?php if (empty($tables)): ?>
        <p>Nessuna tabella trovata.</p>
    <?php else: ?>
        <table>
            <tr><th>#</th><th>Nome Tabella</th></tr>
            <?php foreach ($tables as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($t) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>
