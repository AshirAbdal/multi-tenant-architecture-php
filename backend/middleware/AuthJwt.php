<?php
require_once __DIR__ . '/../dbconnect.php';

define('JWT_PRIVATE_KEY_PATH', __DIR__ . '/../keys/private.pem');
define('JWT_PUBLIC_KEY_PATH',  __DIR__ . '/../keys/public.pem');

function jwt_make(array $payload): string {
    $h          = b64u(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
    $p          = b64u(json_encode($payload));
    $privateKey = openssl_pkey_get_private('file://' . JWT_PRIVATE_KEY_PATH);
    openssl_sign("$h.$p", $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $s = b64u($signature);
    return "$h.$p.$s";
}

function jwt_verify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $publicKey = openssl_pkey_get_public('file://' . JWT_PUBLIC_KEY_PATH);
    $signature = base64_decode(strtr($s, '-_', '+/'));
    $valid     = openssl_verify("$h.$p", $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($valid !== 1) return null;
    $data = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    return ($data['exp'] ?? 0) > time() ? $data : null;
}

function b64u(string $v): string {
    return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
}

function require_auth(): array {
    $token   = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $payload = jwt_verify($token);
    if (!$payload || ($payload['aud'] ?? '') !== 'wl-admin') {
        respond(401, ['error' => 'Unauthorized']);
    }
    return $payload;
}