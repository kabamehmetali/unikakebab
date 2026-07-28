<?php
/**
 * Single shared PDO connection.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('<h2 style="font:16px system-ui;padding:2rem">Database connection failed</h2>'
                  . '<pre style="font:13px ui-monospace;padding:0 2rem;color:#b00">'
                  . htmlspecialchars($e->getMessage())
                  . "\n\nRun:  mysql -u root &lt; sql/schema.sql</pre>");
            }
            die('Service temporarily unavailable.');
        }
    }

    return $pdo;
}

/** Fetch every row for a query. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Fetch a single row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Run an INSERT/UPDATE/DELETE and return the statement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
