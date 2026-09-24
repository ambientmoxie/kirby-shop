<?php if (!empty($_ENV['VITE_DEV'])):
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $host);
    $viteUrl = 'http://' . $host . ':5173';
?>
    <script type="module" src="<?= htmlspecialchars($viteUrl) ?>/@vite/client"></script>
    <script type="module" src="<?= htmlspecialchars($viteUrl) ?>/assets/js/main.js"></script>
<?php else:
    $manifest = json_decode(file_get_contents(kirby()->root() . '/build/.vite/manifest.json'), true);
    $entry = $manifest['assets/js/main.js'];
?>
    <?php if (!empty($entry['css'])): foreach ($entry['css'] as $css): ?>
            <link rel="stylesheet" href="/build/<?= $css ?>">
    <?php endforeach;
    endif; ?>
    <script type="module" src="/build/<?= $entry['file'] ?>"></script>
<?php endif; ?>
