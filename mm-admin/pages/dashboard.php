<?php
if (!defined('APP_ENTRY')) { http_response_code(404); exit; }

const API_BASE = 'http://localhost:8000';
const API_KEY  = 'wl_sk_testkey1234567890abcdef';
const ORIGIN   = 'https://admin.majesticmarquees.com';
// PRIVATE request — X-Tenant-Key + Origin + Authorization: Bearer JWT
$ch = curl_init(API_BASE . '/wl/admin/submissions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'X-Tenant-Key: '    . API_KEY,
        'Origin: '          . ORIGIN,
        'Authorization: Bearer ' . $_SESSION['jwt'],
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$res    = json_decode(curl_exec($ch), true);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 401) {
    // JWT expired or invalid — kick back to login
    session_destroy();
    header('Location: /login');
    exit;
}
$submissions = $res['data'] ?? [];
?>
<script type="application/json" id="page-meta">
{
    "title": "Dashboard — Majestic Marquees Admin",
    "description": "Admin dashboard for Majestic Marquees",
    "og_title": "Dashboard — Majestic Marquees Admin",
    "og_image": ""
}
</script>

<h1>Dashboard — Form Submissions</h1>
<a href="/logout">Logout</a>
<br><br>
<table border="1" cellpadding="8">
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Message</th>
        <th>Date</th>
    </tr>
    <?php foreach ($submissions as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['message']) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($submissions)): ?>
    <tr><td colspan="4">No submissions yet.</td></tr>
    <?php endif; ?>
</table>
