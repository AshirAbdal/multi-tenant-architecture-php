<?php
class AuthController {
    public function login(): void {
        $tenant = validate_tenant_key();
        $body   = json_decode(file_get_contents('php://input'), true);

        $stmt = db()->prepare('SELECT * FROM wl_users WHERE email = ? AND tenant_id = ?');
        $stmt->execute([$body['email'] ?? '', $tenant['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($body['password'] ?? '', $user['password'])) {
            respond(401, ['error' => 'Invalid credentials']);
        }

        respond(200, ['success' => true, 'token' => jwt_make([
            'sub'       => $user['id'],
            'tenant_id' => $tenant['id'],
            'aud'       => 'wl-admin',
            'exp'       => time() + 3600,
        ])]);
    }
}