<?php
/**
 * Auth-Hilfsfunktionen.
 */
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            echo json_encode(['error' => 'not authenticated']);
        } else {
            header('Location: /tippspiel/index.php');
        }
        exit;
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'admin only']);
        exit;
    }
    return $u;
}

function login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = $u['id'];
        return true;
    }
    return false;
}

function register(string $username, string $email, string $pw, string $first, string $last): array {
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    try {
        $stmt = db()->prepare(
            'INSERT INTO users (username,email,password_hash,first_name,last_name) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$username, $email, $hash, $first, $last]);
        return ['ok' => true, 'id' => (int) db()->lastInsertId()];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Username/Email bereits vergeben.'];
    }
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

/** Initialen z.B. fuer Profilbild */
function initials(array $u): string {
    return strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1));
}
