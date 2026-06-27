<?php
/**
 * CLI-only: promotes a user to role='admin' by email or uid. This is the
 * only way to create an admin account — there is intentionally no HTTP
 * endpoint that grants the admin role (see admin_lib.php's docblock for
 * why). Run on the server, e.g.:
 *
 *   php api/admin/bootstrap_admin.php someone@example.com
 *   php api/admin/bootstrap_admin.php <firebase-uid>
 *
 * The target user must already have signed in at least once (so their row
 * exists in `users`); this script does not create accounts, only promotes.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'cli_only']);
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$identifier = trim((string) ($argv[1] ?? ''));
if ($identifier === '') {
    fwrite(STDERR, "Usage: php bootstrap_admin.php <email-or-uid>\n");
    exit(1);
}

$db = uploadgram_db();
$stmt = $db->prepare('SELECT uid, email, display_name, role FROM users WHERE uid = ? OR email = ?');
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user) {
    fwrite(STDERR, "No user found with uid or email \"$identifier\". They must sign in at least once first.\n");
    exit(1);
}

if ($user['role'] === 'admin') {
    echo "{$user['email']} ({$user['uid']}) is already an admin.\n";
    exit(0);
}

$db->prepare("UPDATE users SET role = 'admin' WHERE uid = ?")->execute([$user['uid']]);
echo "Promoted {$user['email']} ({$user['uid']}) to admin.\n";
