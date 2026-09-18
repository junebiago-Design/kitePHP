
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($projectName) ?></title>
    <!-- Renders <link> tags for all CSS files -->
    <?= $this->assets('css'); ?>
</head>