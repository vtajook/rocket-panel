<!doctype html>
<html lang="fa" dir="rtl" class="h-100" data-bs-theme="<?= !empty($settings["theme"]) ? $settings["theme"] : "light" ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rocket SSH - اطلاعات کاربر</title>
    <link rel="icon" type="image/x-icon" href="<?= baseUrl("assets/images/favicon.png") ?>">
    <link href="<?= baseUrl("assets/bootstrap.rtl.min.css") ?>" rel="stylesheet">
    <link href="<?= baseUrl("assets/app.css") ?>" rel="stylesheet">
</head>

<body class="d-flex justify-content-center align-items-center my-5 my-md-0">
    <div class="container">
        <?php include $viewContent ?>
    </div>
    <script src="<?= baseUrl("assets/app.js") ?>"></script>
</body>

</html>