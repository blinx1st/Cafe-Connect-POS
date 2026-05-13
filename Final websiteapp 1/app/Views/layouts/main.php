<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Cafe Connect') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
  </head>
  <body class="<?= e(($page ?? 'website') === 'pos' ? 'pos-body' : '') ?>" data-page="<?= e($page ?? 'website') ?>">
    <script>
      window.CAFE_APP = <?= json_encode($appData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      window.CAFE_INSTALLED = <?= !empty($installed) ? 'true' : 'false' ?>;
      window.CAFE_API_BASE = "<?= e(base_url('api.php')) ?>";
      window.CAFE_BASE_URL = "<?= e(base_url()) ?>";
    </script>
    <?= $content ?>
    <div class="toast" data-toast hidden></div>
    <script src="<?= e(asset_url('js/app.js')) ?>"></script>
  </body>
</html>
