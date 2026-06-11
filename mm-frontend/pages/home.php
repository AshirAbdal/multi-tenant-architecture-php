<?php
if (!defined('APP_ENTRY')) { http_response_code(404); exit; }

const API_BASE = 'http://localhost:8000';
const API_KEY  = 'wl_sk_testkey1234567890abcdef';
const ORIGIN   = 'https://majesticmarquees.com';

$result = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    if (!hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Invalid request.');
    }
    unset($_SESSION['csrf_token']);
    $ch = curl_init(API_BASE . '/wl/forms/contact');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'name'    => $_POST['name']    ?? '',
            'email'   => $_POST['email']   ?? '',
            'message' => $_POST['message'] ?? '',
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Tenant-Key: '    . API_KEY,
            'Origin: '          . ORIGIN,
            'User-Agent: '      . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
            'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? ''),
            
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $result = $status === 200 ? 'Message sent!' : 'Error: ' . $res;
}
?>

<script type="application/json" id="page-meta">
{
    "title": "Contact Us — Majestic Marquees",
    "name": {
        "description":  "Get in touch with Majestic Marquees.",
        "keywords":     "marquee hire, wedding marquee, UK",
        "robots":       "index, follow",
        "author":       "Majestic Marquees"
    },
    "property": {
        "og:title":       "Contact Us — Majestic Marquees",
        "og:description": "Get in touch with Majestic Marquees.",
        "og:image":       "https://majesticmarquees.com/images/contact.jpg",
        "og:type":        "website",
        "og:url":         "https://majesticmarquees.com/contact",
        "twitter:card":   "summary_large_image",
        "twitter:title":  "Contact Us — Majestic Marquees"
    },
    "schema": {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Majestic Marquees",
        "description": "Premium marquee hire services for weddings and events in the UK",
        "url": "https://majesticmarquees.com",
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "Customer Service",
            "url": "https://majesticmarquees.com/contact"
        }
    }
}
</script>

<h1>Contact Us</h1>
<?php if ($result): ?><p><?= htmlspecialchars($result) ?></p><?php endif; ?>
<form method="POST" action="/">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input    name="name"    placeholder="Your name"  required><br><br>
    <input    name="email"   placeholder="Your email" type="email" required><br><br>
    <textarea name="message" placeholder="Message"    required></textarea><br><br>
    <button type="submit">Send</button>
</form>
