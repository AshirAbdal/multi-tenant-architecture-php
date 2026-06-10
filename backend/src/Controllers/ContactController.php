<?php
class ContactController {
    public function store(): void {
        $tenant = validate_tenant_key();
        $body   = json_decode(file_get_contents('php://input'), true);

        db()->prepare('INSERT INTO wl_form_submissions (tenant_id, name, email, message) VALUES (?,?,?,?)')
           ->execute([$tenant['id'], $body['name'] ?? '', $body['email'] ?? '', $body['message'] ?? '']);

        respond(200, ['success' => true]);
    }
}