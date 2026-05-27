<?php
/**
 * ============================================================
 * Auth-Helpers: Login / Logout / Session-Check / Registrierung
 * ------------------------------------------------------------
 * Wird von praktisch jeder Seite eingebunden, damit
 *  - die Session sicher gestartet ist
 *  - geprueft wird ob ein User eingeloggt ist
 *  - Admin-Rollen erzwungen werden koennen
 * ============================================================
 */
require_once __DIR__ . '/../config/db.php';

// Sicherstellen dass eine Session laeuft (idempotent)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Holt den aktuell eingeloggten User (oder null).
 * Caching innerhalb des Requests: einmal aus der DB, danach Memory.
 *
 * @return array|null  User-Row oder null wenn nicht eingeloggt
 */
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;                            // Cache pro Request
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

/**
 * Erzwingt einen eingeloggten User.
 *  - API-Anfragen bekommen 401 + JSON-Fehler
 *  - Browser-Anfragen werden zur Login-Seite redirected
 *
 * @return array  User-Row (garantiert nicht null)
 */
function require_login(): array {
    $u = current_user();
    if (!$u) {
        // Unterscheide API (JSON) und Page (HTML-Redirect)
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            echo json_encode(['error' => 'not authenticated']);
        } else {
            header('Location: /Tippspiel/index.php');
        }
        exit;
    }
    return $u;
}

/**
 * Wie require_login(), aber zusaetzlich Admin-Rolle erforderlich.
 *
 * @return array  User-Row mit role='admin'
 */
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'admin only']);
        exit;
    }
    return $u;
}

/**
 * Login per Username ODER E-Mail + Passwort.
 *
 * @param string $username  Username oder E-Mail
 * @param string $password  Klartext-Passwort
 * @return bool             true bei Erfolg (Session gesetzt)
 */
function login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $u = $stmt->fetch();
    // password_verify akzeptiert sowohl $2y$ (PHP) als auch $2b$ (bcrypt)
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = $u['id'];
        return true;
    }
    return false;
}

/**
 * Neuen User anlegen. Passwort wird mit bcrypt gehasht.
 *
 * @return array  ['ok'=>true,'id'=>...] oder ['ok'=>false,'error'=>...]
 */
function register(string $username, string $email, string $pw, string $first, string $last): array {
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    try {
        $stmt = db()->prepare(
            'INSERT INTO users (username,email,password_hash,first_name,last_name) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$username, $email, $hash, $first, $last]);
        return ['ok' => true, 'id' => (int) db()->lastInsertId()];
    } catch (PDOException $e) {
        // UNIQUE-Constraint auf username/email -> "schon vergeben"
        return ['ok' => false, 'error' => 'Username/Email bereits vergeben.'];
    }
}

/**
 * Logout: Session-Daten loeschen und Session zerstoeren.
 */
function logout(): void {
    $_SESSION = [];
    session_destroy();
}

/**
 * Liefert die Initialen eines Users (z.B. "NZ" fuer Noah Zuercher).
 * Wird in der Avatar-Bubble im Header benutzt.
 */
function initials(array $u): string {
    return strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1));
}
