<?php

// ============================================================
//  csrf.php — Returns a CSRF token tied to the current session.
//
//  What is CSRF? Cross-Site Request Forgery is an attack where
//  a malicious website tricks your browser into making requests
//  to OUR server using YOUR session cookie.
//  A CSRF token prevents this: it's a secret value that only
//  legitimate pages can read and include in requests.
//
//  Flow:
//    1. JS calls GET php/csrf.php → gets a token
//    2. JS stores the token and sends it as X-CSRF-Token header
//       in every POST request
//    3. PHP endpoints check the header matches the session token
// ============================================================

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Generate a random token for this session if it doesn't exist yet.
// bin2hex(random_bytes(32)) produces a 64-character hex string — cryptographically secure.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['csrf_token']]);
