<?php if (!defined('APP_ENTRY')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageMeta['title'] ?? 'Majestic Marquees', ENT_QUOTES) ?></title>
    <?php foreach ($pageMeta['name'] ?? [] as $k => $v): ?>
    <meta name="<?= htmlspecialchars($k, ENT_QUOTES) ?>" content="<?= htmlspecialchars($v, ENT_QUOTES) ?>">
    <?php endforeach; ?>
    <?php foreach ($pageMeta['property'] ?? [] as $k => $v): ?>
    <meta property="<?= htmlspecialchars($k, ENT_QUOTES) ?>" content="<?= htmlspecialchars($v, ENT_QUOTES) ?>">
    <?php endforeach ?>
    <?php if (!empty($pageMeta['schema'])): ?>
    <script type="application/ld+json"><?= json_encode($pageMeta['schema']) ?></script>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/spa.js" defer></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">

<nav class="bg-[#1a1a2e] px-8 flex items-center h-14" id="main-nav">
    <a href="/" class="spa-link text-gray-200 font-bold text-lg no-underline mr-auto">Majestic Marquees</a>
    <a href="/"      class="spa-link text-[#a0a0c0] text-sm px-3 py-1 rounded transition-colors">Home</a>
    <a href="/about" class="spa-link text-[#a0a0c0] text-sm px-3 py-1 rounded transition-colors">About</a>
</nav>

<main class="max-w-4xl mx-auto my-10 px-6" id="content">
    <?= $pageContent ?? '' ?>
</main>

<footer class="text-center text-sm text-gray-400 py-6">
    &copy; 2026 Majestic Marquees
</footer>

</body>
</html>
