<?php
/**
 * MySQL persistence layer (PDO). Stores everything the upstream reseller
 * API and Firebase Auth don't: user profiles, wallet balances, orders,
 * support tickets, locally-managed products, and admin accounts.
 *
 * Run via phpMyAdmin or any MySQL client against the same database the
 * connection settings in config.php point at — uploadgram_db() creates
 * every table it needs on first use (idempotent CREATE TABLE IF NOT EXISTS),
 * so there is no separate migration step to run.
 */

require_once __DIR__ . '/config.php';

function uploadgram_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = uploadgram_mysql_config();
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    uploadgram_migrate($pdo);

    return $pdo;
}

function uploadgram_migrate(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            uid VARCHAR(128) PRIMARY KEY,
            email VARCHAR(255) DEFAULT NULL,
            display_name VARCHAR(255) DEFAULT NULL,
            username VARCHAR(64) DEFAULT NULL,
            phone VARCHAR(32) DEFAULT NULL,
            balance BIGINT NOT NULL DEFAULT 0,
            role VARCHAR(16) NOT NULL DEFAULT 'user',
            is_suspended TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(128) DEFAULT NULL,
            platform VARCHAR(32) NOT NULL DEFAULT 'other',
            source VARCHAR(16) NOT NULL DEFAULT 'upstream',
            upstream_service_id VARCHAR(64) DEFAULT NULL,
            api_url VARCHAR(500) DEFAULT NULL,
            api_key VARCHAR(255) DEFAULT NULL,
            api_action VARCHAR(64) DEFAULT NULL,
            base_rate DECIMAL(14,4) DEFAULT NULL,
            min_qty INT DEFAULT NULL,
            max_qty INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uid VARCHAR(128) NOT NULL,
            product_id INT DEFAULT NULL,
            service_id VARCHAR(64) NOT NULL,
            service_name VARCHAR(255) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            link VARCHAR(500) NOT NULL,
            quantity INT NOT NULL,
            unit_rate DECIMAL(14,4) NOT NULL,
            total_price BIGINT NOT NULL,
            pay_method VARCHAR(16) NOT NULL DEFAULT 'zarinpal',
            status VARCHAR(24) NOT NULL DEFAULT 'awaiting_payment',
            payment_authority VARCHAR(64) DEFAULT NULL,
            payment_ref_id VARCHAR(64) DEFAULT NULL,
            upstream_order_id VARCHAR(64) DEFAULT NULL,
            upstream_status VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_orders_uid (uid),
            INDEX idx_orders_authority (payment_authority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uid VARCHAR(128) NOT NULL,
            type VARCHAR(16) NOT NULL,
            amount BIGINT NOT NULL,
            authority VARCHAR(64) DEFAULT NULL,
            ref_id VARCHAR(64) DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wallet_uid (uid),
            INDEX idx_wallet_authority (authority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uid VARCHAR(128) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tickets_uid (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            sender VARCHAR(16) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket_messages_ticket (ticket_id),
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value TEXT,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(128) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            body MEDIUMTEXT,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/** Creates the user row on first sight (idempotent) and returns the current profile row. */
function uploadgram_get_or_create_user(string $uid, string $email = '', string $displayName = ''): array {
    $db = uploadgram_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE uid = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $insert = $db->prepare('INSERT INTO users (uid, email, display_name) VALUES (?, ?, ?)');
    $insert->execute([$uid, $email, $displayName]);

    $stmt->execute([$uid]);
    return $stmt->fetch();
}

function uploadgram_is_admin(string $uid): bool {
    $db = uploadgram_db();
    $stmt = $db->prepare('SELECT role FROM users WHERE uid = ?');
    $stmt->execute([$uid]);
    $role = $stmt->fetchColumn();
    return $role === 'admin';
}
