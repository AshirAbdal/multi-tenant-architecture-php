<?php
require_once __DIR__ . '/../dbconnect.php';

function validate_tenant_key(): array {
    $rawKey = $_SERVER['HTTP_X_TENANT_KEY'] ?? '';
    if (!$rawKey) {
        respond(401, ['error' => 'Missing X-Tenant-Key']);
    }

    // Extract root domain from Origin (strip any subdomain)
    // blog.majesticmarquees.com → majesticmarquees.com
    // admin.majesticmarquees.com → majesticmarquees.com
    // majesticmarquees.com → majesticmarquees.com
    $host  = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST) ?? '';
    $parts = explode('.', $host);
    $rootDomain = count($parts) >= 2
        ? implode('.', array_slice($parts, -2))
        : $host;

    // Verify key AND primary domain in one query
    $stmt = db()->prepare(
        'SELECT * FROM wl_tenants WHERE api_key_hash = ? AND primary_domain = ?'
    );
    $stmt->execute([hash('sha256', $rawKey), $rootDomain]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not matched by root domain, check wl_tenant_domains for custom domains
    // e.g. tenant owns "myevents.co.uk" which is unrelated to their primary domain
    if (!$tenant) {
        $stmt2 = db()->prepare(
            'SELECT t.* FROM wl_tenants t
             JOIN wl_tenant_domains d ON d.tenant_id = t.id
             WHERE t.api_key_hash = ? AND d.domain = ? AND d.is_active = 1'
        );
        $stmt2->execute([hash('sha256', $rawKey), $host]);
        $tenant = $stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            respond(401, ['error' => 'Invalid key or origin']);
        }
    } else {
        // Tenant matched by root domain — now double-verify the full host
        // exists in wl_tenant_domains (for subdomains like blog., admin., shop.)
        // Primary domain itself is always allowed — only subdomains need registration
        if ($host !== $rootDomain) {
            $stmt3 = db()->prepare(
                'SELECT id FROM wl_tenant_domains
                 WHERE tenant_id = ? AND domain = ? AND is_active = 1'
            );
            $stmt3->execute([$tenant['id'], $host]);
            if (!$stmt3->fetch()) {
                respond(403, ['error' => 'Subdomain not registered']);
            }
        }
    }

    if ($tenant['status'] === 'suspended') {
        respond(503, ['error' => 'Tenant suspended']);
    }

    return $tenant;
}