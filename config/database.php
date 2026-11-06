<?php
/**
 * CoachFee – Database Connection (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In production, log this error rather than displaying it
                if (APP_ENV === 'development') {
                    die('<div style="font-family:sans-serif;color:#D96868;padding:2rem;">
                         <h2>Database Connection Failed</h2>
                         <p>' . htmlspecialchars($e->getMessage()) . '</p>
                         <p>Check your config/config.php settings and ensure MySQL is running.</p>
                         </div>');
                } else {
                    die('Service temporarily unavailable. Please try again later.');
                }
            }
        }
        return self::$instance;
    }

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
}

/**
 * Convenience function to get PDO instance
 */
function db(): PDO {
    return Database::getInstance();
}
