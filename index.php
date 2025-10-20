
<?php
// index.php
// Redirect to admin/login when the site root is opened.
// Change $target if you want to redirect to "admin/login" (without .php) or another path.

$target = '/admin/login.php'; // <-- adjust to '/admin/login' if your admin route doesn't use .php

// Use a 302 (temporary) redirect. Change to 301 if you'd like a permanent redirect.
header('Location: ' . $target, true, 302);
exit;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Redirecting…</title>
  <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">
  <script>
    // JS fallback for browsers that do not follow the header or meta refresh
    window.location.replace("<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>");
  </script>
</head>
<body>
  <p>Redirecting to <a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?></a> …</p>
</body>
</html>
