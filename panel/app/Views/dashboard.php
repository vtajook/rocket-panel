<style>
    .custom-card {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
    }

    .card {
        position: relative;
    }

    .widget-title {
        font-size: 14px;
    }

    .widget-icon {
        line-height: 15px;
    }
</style>
<div class="row gx-3">
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3">کل کاربران</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary"><?= $totalData["users"]["all"] ?></h5>
                    <span class="widget-icon fs-3 text-primary">
                        <?= inlineIcon("users") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users/online") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3">کاربران آنلاین</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-success"><?= $totalData["users"]["online"] ?></h5>
                    <span class="widget-icon fs-3 text-success">
                        <?= inlineIcon("earth-americas") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users?status=active") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3">کاربران فعال</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-info"><?= $totalData["users"]["active"] ?></h5>
                    <span class="widget-icon fs-3 text-info">
                        <?= inlineIcon("users-line") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users?status=de_active") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3">کاربران غیر فعال</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-warning"><?= $totalData["users"]["inActive"] ?></h5>
                    <span class="widget-icon fs-3 text-warning">
                        <?= inlineIcon("users-slash") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users?status=expiry_traffic") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3"> کاربران انقضای ترافیک</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-danger"><?= $totalData["users"]["expiryTraffic"] ?></h5>
                    <span class="widget-icon fs-3 text-danger">
                        <?= inlineIcon("cloud-slash") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-4 col-xl-2 mb-4">
        <a href="<?= baseUrl("users?status=expiry_date") ?>" class="card">
            <div class="card-body">
                <h6 class="widget-title mb-3"> کاربران انقضای تاریخ</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-danger"><?= $totalData["users"]["expiryDate"] ?></h5>
                    <span class="widget-icon fs-3 text-danger">
                        <?= inlineIcon("calendar") ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
</div>
<div class="row gx-3">
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">مصرف RAM</h6>
                    <span class="widget-icon fs-3 text-primary">
                        <?= inlineIcon("memory") ?>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between flex-row-reverse mt-1">
                        <div class="unicode-bidi-plain small"> <b><?= $ramData["total"] ?></b></div>
                        <div><?= $ramData["usage_percent"] ?>%</div>
                    </div>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" style="background-color:<?= $ramData["usage_color"] ?>;color:<?= $ramData["usage_text_color"] ?>; width: <?= $ramData["usage_percent"] ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">مصرف CPU</h6>
                    <span class="widget-icon fs-3 text-info">
                        <?= inlineIcon("microchip") ?>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between flex-row-reverse mt-1">
                        <div><span class="text-muted small">Cores:</span> <b><?= $cpuData["totalCores"] ?></b></div>
                        <div><?= $cpuData['loadAvg'] ?>%</div>
                    </div>

                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" style="color:<?= $cpuData["usage_text_color"] ?>;background-color:<?= $cpuData["usage_color"] ?>;width: <?= $cpuData['loadAvg'] ?>%"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">مصرف هارد</h6>
                    <span class="widget-icon fs-3 text-success">
                        <?= inlineIcon("hard-drive") ?>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between flex-row-reverse mt-1">
                        <div class="unicode-bidi-plain small"><b><?= $diskData["total"] ?></b></div>
                        <div><?= $diskData['usage_percent'] ?>%</div>
                    </div>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" style="color:<?= $diskData["usage_text_color"] ?>;background-color:<?= $diskData["usage_color"] ?>;width: <?= $diskData['usage_percent'] ?>%"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">ترافیک سرور</h6>
                    <span class="widget-icon fs-3 text-primary">
                        <?= inlineIcon("server") ?>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="fs-6">
                        <?= $serverTraffic["total"] ?>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small title="دانلود" class="me-2">
                            <?= inlineIcon("download") ?> <?= $serverTraffic["download"] ?>
                        </small>
                        <small title="آپلود">
                            <?= inlineIcon("upload") ?> <?= $serverTraffic["upload"] ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">ترافیک کاربران</h6>
                    <span class="widget-icon fs-3 text-warning">
                        <?= inlineIcon("users") ?>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="fs-6">
                        <?= $userTraffic["total"] ?>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small title="دانلود" class="me-2">
                            <?= inlineIcon("download") ?> <?= $userTraffic["download"] ?>
                        </small>
                        <small title="آپلود">
                            <?= inlineIcon("upload") ?> <?= $userTraffic["upload"] ?>
                        </small>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg-4 col-xl-2 mb-4">
        <div class="card h-100">
            <div class="card-body custom-card ">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="widget-title mb-2">آپ تایم سرور</h6>
                    <button title="ریبوت سیستم عامل" class="rounded-circle btn btn-sm btn-danger btn-reboot-server">
                        <?= inlineIcon("power-off") ?>
                    </button>
                </div>
                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <h6 class="small"><?= $uptime ?></h6>

                </div>
            </div>
        </div>
    </div>
</div>