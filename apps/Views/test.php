<!-- View: TestController | Generated from view.php.kite -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test | Demo</title>
    <?= $this->assets('css'); ?>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/"><?= htmlspecialchars($projectName) ?></a>
    </div>
</nav>

    <!-- Main content wrapper: grows to push footer down -->
    <main class="flex-grow-1">
        <div class="container mt-4 p-5 bg-dark text-light rounded">
            <h1><?= htmlspecialchars(ucfirst($viewName)) ?> Page</h1>
            <subtitle>Congratulations! Youve created a Controller that routes to your view files is now ready</subtitle>
            <p><b>Controller Name:</b> <?= htmlspecialchars($projectName) ?> <b>Namespace:</b> <?= htmlspecialchars($namespace) ?></p>
            <button type="button" class="btn btn-primary" onclick="window.open('https://tuss.rf.gd', '_blank', 'noopener,noreferrer');">
                Read More
            </button>
        </div>
    </main>

    <!-- Footer: mt-auto pushes it to the bottom -->
    <footer class="mt-auto bg-dark text-light rounded text-center" >
        <div class="container py-4">
            <p class="mb-1">&copy; 2026 KitePHP. All rights reserved.</p>
            <ul class="list-inline mb-0" text-light rounded>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Privacy Policy</a></li>
      <li class="list-inline-item">&middot;</li>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Terms of Service</a></li>
      <li class="list-inline-item">&middot;</li>
      <li class="list-inline-item"><a href="" class="text-secondary text-decoration-none">Contact</a></li>
    </ul>
          
        </div>
    </footer>

    <?= $this->assets('js'); ?>
</body>
</html>