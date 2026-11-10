<?php
$mainAppEnqueue = getMainAppEnqueue();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="<?= $activeTheme ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title><?= $pageTitle ?></title>
    <!-- Icons -->
    <link rel="icon" type="image/x-icon" href="<?= assets("images/favicon.png") ?>">
    <link rel="stylesheet" href="<?= assets("fonts/fontawsome.css") ?>" />
    <link rel="stylesheet" href="<?= assets("fonts/webfont.css") ?>" />
    <?= headerFiles() ?>
    <link rel="stylesheet" href="<?= assets("css/sweetalert2.css") ?>">
    <link rel="stylesheet" href="<?= $mainAppEnqueue["css_url"] ?>">
    <script src="<?= assets("vendor/jquery/jquery.js") ?>"></script>
    <script>
        const base_url = "<?= baseUrl() ?>";
        const user_role = "<?= !empty($userRole)      ? $userRole : "" ?>";
        const activePage = "<?= !empty($activePage)    ? $activePage : "" ?>";

        var baseUrl = function(seg = "") {
            return base_url + seg;
        };

        var assets = function(seg = "") {
            return baseUrl("assets/" + seg);
        };
    </script>
</head>

<body>
    <style>
        body {
            background-image: url(<?= assets("images/img-auth-bg.jpg") ?>);
            background-size: cover;
            background-attachment: fixed;
            background-position-x: center;
            background-position-y: center;
        }

        [data-bs-theme="dark"] body {
            background-image: url(<?= assets("images/img-auth-bg-dark.jpg") ?>);
        }
    </style>