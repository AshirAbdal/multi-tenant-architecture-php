<?php if (!defined('APP_ENTRY')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Majestic Marquees — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">

<nav class="bg-[#1a1a2e] px-8 flex items-center h-14">
    <span class="text-gray-200 font-bold text-lg">Majestic Marquees <span class="text-xs font-normal text-yellow-400 ml-1">Admin</span></span>
</nav>

<main class="max-w-4xl mx-auto my-10 px-6">
    <?= $pageContent ?? '' ?>
</main>

</body>
</html>
