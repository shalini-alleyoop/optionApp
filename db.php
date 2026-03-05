<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}

function upsert_shop_token(string $shop_domain, string $token): void {
    $sql = "INSERT INTO shops (shop_domain, access_token) VALUES (:shop, :token)
            ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), updated_at = NOW()";
    $stmt = db()->prepare($sql);
    $stmt->execute([':shop' => $shop_domain, ':token' => $token]);
}

function get_shop(string $shop_domain): ?array {
    $stmt = db()->prepare("SELECT * FROM shops WHERE shop_domain = :shop LIMIT 1");
    $stmt->execute([':shop' => $shop_domain]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function log_webhook(string $shop_domain, string $topic, string $payload): void {
    $stmt = db()->prepare("INSERT INTO webhook_logs (shop_domain, topic, payload) VALUES (:shop, :topic, :payload)");
    $stmt->execute([':shop' => $shop_domain, ':topic' => $topic, ':payload' => $payload]);
}
