<?php
/**
 * Run Migration V5 - Add target_audience field to events table
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    // Read and execute the migration
    $migration = file_get_contents(__DIR__ . '/migration_v5.sql');

    // Remove comments and split by semicolons
    $statements = array_filter(
        array_map('trim', explode(';', $migration)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $pdo->beginTransaction();

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }

    $pdo->commit();

    echo "\n✓ Migration V5 completed successfully!\n";
    echo "Added target_audience field to events table.\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
