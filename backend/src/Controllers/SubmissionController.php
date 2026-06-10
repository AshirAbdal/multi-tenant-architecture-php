<?php
class SubmissionController {
    public function index(): void {
        $tenant = validate_tenant_key();
        require_auth();                  // checks JWT + aud='wl-admin'

        $stmt = db()->prepare(
            'SELECT id, name, email, message, created_at
             FROM wl_form_submissions
             WHERE tenant_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$tenant['id']]);

        respond(200, ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}