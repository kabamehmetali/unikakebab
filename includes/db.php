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

            // 503, never 200. An outage answered with "Service temporarily
            // unavailable" and a success status reads to a crawler as a page
            // whose entire content is that sentence — and it will replace the
            // real page in the index with it. 503 with Retry-After says "come
            // back, keep what you have", which is what is actually true.
            error_log('Database connection failed: ' . $e->getMessage());

            if (function_exists('render_error_page')) {
                render_error_page(503, ['retry' => 120]);
            }

            // Only reachable if this file is used outside the normal bootstrap.
            http_response_code(503);
            header('Retry-After: 120');
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
