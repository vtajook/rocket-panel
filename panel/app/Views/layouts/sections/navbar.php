<div class=" rounded border-bottom shadow-sm">
    <header class="main-header d-flex justify-content-between p-1">
        <div class="header-toggle d-block d-lg-none ">
            <button class="menu-toggle bt-sm  btn btn-primary rounded-circle ">
                <?= inlineIcon("bars","align-middle") ?>
            </button>
        </div>
        <div class="d-flex align-items-center">
           <div class="ps-2"> <?= $userRole == "admin" ? "مدیر" : "نماینده" ?>: <?= htmlspecialchars($userInfo["fullname"]) ?></div>
           <?php if (session()->get("imp_admin")) { ?>
               <a class="btn btn-sm btn-warning ms-2" href="<?= baseUrl("agency/back") ?>">بازگشت به پنل مدیر</a>
           <?php } ?>
        </div>
        <div class="m-auto"></div>
        <div class="d-flex align-items-center">
            <div class="me-3">امروز: <?= getCurrentDate() ?></div>
            <button type="button" id="btn-toggle-theme" class="btn btn-toggle-theme btn-sm" data-theme="<?= $activeTheme ?>" aria-pressed="false">
                <?= inlineIcon($activeTheme == "light" ? "sun" : "moon-stars") ?>
            </button>
        </div>
    </header>
</div>