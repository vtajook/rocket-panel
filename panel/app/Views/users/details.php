<?php
$userValues         = isset($userValues) ? $userValues : null;
$userId             = getArrayValue($userValues, "id");
$username           = getArrayValue($userValues, "username");
$password           = getArrayValue($userValues, "password");
$email              = getArrayValue($userValues, "email");
$mobile             = getArrayValue($userValues, "mobile");
$traffic            = getArrayValue($userValues, "format_traffic");
$startDate          = getArrayValue($userValues, "start_date");
$endDate            = getArrayValue($userValues, "end_date");
$expiryDays         = getArrayValue($userValues, "expiry_days");
$expiryType         = getArrayValue($userValues, "expiry_type");
$concurrentUsers    = getArrayValue($userValues, "limit_users");
$consumerTraffic    = getArrayValue($userValues, "format_consumer_traffic");
$endDateJD          = getArrayValue($userValues, "end_date_jd"); //jalali date
$startJD            = getArrayValue($userValues, "start_date_jd"); //jalali date
$remainingDays      = getArrayValue($userValues, "remaining_days", 0);
$netmodQrUrl        = getArrayValue($userValues, "netmod_qr_url", "");
$npvQrUrl           = getArrayValue($userValues, "npv_qr_url", "");
$status             = getArrayValue($userValues, "status", "");
$adminName          = getArrayValue($userValues, "admin_name", "");
$desc               = getArrayValue($userValues, "desc", "");
$arrayConfig        = getArrayValue($userValues, "array_config", []);
$diffrenceDate      = getArrayValue($userValues, "diffrence_date", "");
$publicLink         = getArrayValue($userValues, "public_link", "");


$remainingText = "";
if ($remainingDays >= 0) {
    $remainingText = "$remainingDays روز دیگر";
} else if ($remainingDays < 0) {
    $remainingText = "<span class='text-danger'>" . abs($remainingDays) . " روز گذشته</span>";
}

$editViewUrl = "users/$userId/edit?ref=details";

$values = [
    [
        "label" => "نام کاربری",
        "value" => $username,
    ],
    [
        "label" => "رمز عبور",
        "value" => "<span class='cursor-pointer' data-copy='true' data-text='$password' data-bs-toggle='tooltip' title='کپی'>$password</span>",
    ],
    [
        "label" =>  "ترافیک",
        "value" => $traffic,
    ],
    [
        "label" => "ترافیک مصرفی",
        "value" => $consumerTraffic ? "<span id='spn-user-traffic'>$consumerTraffic</span>" : 0
    ],
    [
        "label" =>  "تاریخ شروع",
        "value" => $startJD,
    ],
    [
        "label" =>  "مدت زمان",
        "value" => $diffrenceDate,
    ],
    [
        "label" => "تاریخ پایان",
        "value" => $endDateJD,
    ],
    [
        "label" =>  "زمان باقی مانده",
        "value" => $remainingText,
    ],
    [
        "label" =>  "تعداد کاربران",
        "value" => $concurrentUsers,
    ],
    [
        "label" => "ایمیل",
        "value" => $email,
    ],
    [
        "label" => "موبایل",
        "value" => $mobile,
    ],
    [
        "label" => "لینک عمومی کاربر",
        "value" => "<a target='_blank' href='$publicLink'>$publicLink</a>",
    ],
    [
        "label" => "ثبت کننده",
        "value" => $adminName,
    ],
    [
        "label" => "توضیحات",
        "value" => $desc,
    ],
];

?>

<div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">اطلاعات کاربر</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body ">
            <div class="row">
                <div class="col-lg-8 border-end">
                    <?php
                    foreach ($values as  $item) {
                        $label = $item["label"];
                        $value = $item["value"];
                    ?>
                        <div class="d-flex flex-row align-items-center justify-content-between border-bottom py-2 px-3">
                            <span class="text-muted"><?= $label ?></span>
                            <span class=" fw-bold"><?= $value ? $value : "-" ?></span>
                        </div>
                    <?php
                    }
                    ?>
                </div>
                <div class="col-lg-4">
                    <div class="p-1 h-100">
                        <div class="row justify-content-between align-items-center h-100">
                            <div class="col-6 col-sm-6 px-1 col-md-12">
                                <div class="actions">
                                    <button class="btn btn-primary w-100 mb-2 btn-float-icon btn-ajax-views" data-url="<?= $editViewUrl ?>">
                                        <?= inlineIcon("edit") ?>
                                        ویرایش
                                    </button>


                                    <button class="btn-chng-active btn mb-2 btn-<?= $status == "active" ? "warning" : "success" ?> w-100 mb-2 btn-float-icon" data-active="<?= $status == "active" ? 1 : 0 ?>" data-id="<?= $userId ?>">
                                        <?= inlineIcon($status == "active" ? "pause" : "play") ?>
                                        <?= $status == "active" ? "غیر فعال کردن" : "فعال کردن" ?>
                                    </button>


                                    <?php if ($userRole == "admin") { ?>
                                        <button class="btn-reset-traffic btn btn-danger w-100 mb-2 btn-float-icon " data-id="<?= $userId ?>">
                                            <?= inlineIcon("rotate-left") ?>
                                            ریست ترافیک
                                        </button>
                                    <?php } ?>

                                    <button class="btn-copy-config btn btn-secondary w-100 mb-2 btn-float-icon btn-copy-config" data-config="<?= htmlspecialchars(json_encode($arrayConfig, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
                                        <?= inlineIcon("copy") ?>
                                        کپی کانفیگ
                                    </button>
                                </div>

                            </div>
                            <div class="col-6 col-sm-6 px-1 col-md-12">
                                <div class="border-md-top mt-2">
                                    <div class="my-2"><small>دریافت کانفیگ (برنامه‌ی مورد نظر را انتخاب کنید)</small></div>
                                    <ul class="nav nav-pills nav-fill mb-2 small" role="tablist">
                                        <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="pill" data-bs-target="#cfg-nm-<?= $userId ?>" type="button">NetMod</button></li>
                                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#cfg-npv-<?= $userId ?>" type="button">NPV</button></li>
                                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#cfg-sub-<?= $userId ?>" type="button">ساب</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cfg-nm-<?= $userId ?>">
                                            <img src="<?= $netmodQrUrl ?>" width="100%" />
                                            <button class="btn btn-secondary btn-sm w-100 mt-1" data-copy="true" data-text="<?= htmlspecialchars($arrayConfig["netmod_link"] ?? "", ENT_QUOTES) ?>">کپی لینک NetMod</button>
                                        </div>
                                        <div class="tab-pane fade" id="cfg-npv-<?= $userId ?>">
                                            <img src="<?= $npvQrUrl ?>" width="100%" />
                                            <button class="btn btn-secondary btn-sm w-100 mt-1" data-copy="true" data-text="<?= htmlspecialchars($arrayConfig["npv_link"] ?? "", ENT_QUOTES) ?>">کپی لینک NPV</button>
                                        </div>
                                        <div class="tab-pane fade" id="cfg-sub-<?= $userId ?>">
                                            <div class="small text-muted mb-1">لینک ساب: در برنامه افزوده شود، روز و حجم باقی‌مانده نشان داده می‌شود</div>
                                            <input class="form-control form-control-sm text-start" dir="ltr" readonly value="<?= htmlspecialchars($arrayConfig["sub_link"] ?? "", ENT_QUOTES) ?>" onclick="this.select()">
                                            <button class="btn btn-secondary btn-sm w-100 mt-1" data-copy="true" data-text="<?= htmlspecialchars($arrayConfig["sub_link"] ?? "", ENT_QUOTES) ?>">کپی لینک ساب</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>