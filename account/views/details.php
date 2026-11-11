<?php
$userId             = getArrayValue($userInfo, "id");
$username           = getArrayValue($userInfo, "username");
$password           = getArrayValue($userInfo, "password");
$email              = getArrayValue($userInfo, "email");
$mobile             = getArrayValue($userInfo, "mobile");
$traffic            = getArrayValue($userInfo, "format_traffic");
$startDate          = getArrayValue($userInfo, "start_date");
$endDate            = getArrayValue($userInfo, "end_date");
$expiryDays         = getArrayValue($userInfo, "expiry_days");
$expiryType         = getArrayValue($userInfo, "expiry_type");
$concurrentUsers    = getArrayValue($userInfo, "limit_users");
$consumerTraffic    = getArrayValue($userInfo, "format_consumer_traffic");
$endDateJD          = getArrayValue($userInfo, "end_date_jd"); //jalali date
$startJD            = getArrayValue($userInfo, "start_date_jd"); //jalali date
$remainingDays      = getArrayValue($userInfo, "remaining_days", 0);
$netmodQrUrl        = getArrayValue($userInfo, "netmod_qr_url", "");
$npvQrUrl           = getArrayValue($userInfo, "npv_qr_url", "");
$status             = getArrayValue($userInfo, "status", "");
$arrayConfig        = getArrayValue($userInfo, "array_config", []);
$diffrenceDate      = getArrayValue($userInfo, "diffrence_date", "");
$sshPort            = getArrayValue($arrayConfig, "ssh_port", "");
$udpPort            = getArrayValue($arrayConfig, "udp_port", "");
$host               = getArrayValue($arrayConfig, "host", "");

$remainingText = "";
if ($remainingDays >= 0) {
    $remainingText = "$remainingDays روز دیگر";
} else if ($remainingDays < 0) {
    $remainingText = "<span class='text-danger'>" . abs($remainingDays) . " روز گذشته</span>";
}

$values = [
    [
        "label" => "نام کاربری",
        "value" => $username,
    ],
    [
        "label" => "رمز عبور",
        "value" => $password,
    ],
    [
        "label" => "HOST",
        "value" => $host
    ],
    [
        "label" => "پورت SSH",
        "value" => $sshPort
    ],
    [
        "label" => "پورت UPD",
        "value" => $udpPort
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
];

?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="card shadow-lg border-0">
            <div class="card-header border-bottom-0 py-3">

                اطلاعات پروفایل شما
            </div>
            <div class="card-body">
                <?php if (!empty($settings["logo_url"])) { ?>
                    <div class="text-center">
                        <img src="<?= $settings["logo_url"] ?>" width="100" />
                    </div>
                <?php } ?>
                <?php if (!empty($settings["welecom_text"])) { ?>
                    <div class="text-center border-bottom py-2"><?= $settings["welecom_text"] ?></div>
                <?php } ?>
                <div class="row">
                    <div class="col-lg-8 border-end">
                        <?php
                        foreach ($values as  $key => $item) {
                            $label = $item["label"];
                            $value = $item["value"];
                            $class = "d-flex flex-row align-items-center justify-content-between py-2 px-3";
                            if ($key < count($values) - 1) {
                                $class .= " border-bottom";
                            }
                        ?>
                            <div class="<?= $class ?>">
                                <span class="text-muted small"><?= $label ?></span>
                                <span class=" fw-bold"><?= $value ? $value : "-" ?></span>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="col-lg-4 text-center">
                        <div class="d-flex h-100 justify-content-around flex-column">
                            <div class="mb-3">
                                <p class="small mb-2">برنامه‌ی خود را انتخاب کنید و کد را اسکن یا لینک را کپی کنید</p>
                                <div class="btn-group btn-group-sm mb-2" role="group">
                                    <button type="button" class="btn btn-primary cfg-tab" data-t="nm">NetMod</button>
                                    <button type="button" class="btn btn-outline-primary cfg-tab" data-t="npv">NPV</button>
                                    <button type="button" class="btn btn-outline-primary cfg-tab" data-t="sub">ساب</button>
                                </div>
                                <div class="cfg-pane" data-t="nm">
                                    <img class="m-auto" src="<?= $netmodQrUrl ?>" style="max-width:100%" />
                                    <button type="button" class="btn btn-secondary btn-sm d-block m-auto mt-2 cfg-copy" data-text="<?= htmlspecialchars(getArrayValue($arrayConfig, "netmod_link", ""), ENT_QUOTES) ?>">کپی لینک NetMod</button>
                                </div>
                                <div class="cfg-pane" data-t="npv" style="display:none">
                                    <img class="m-auto" src="<?= $npvQrUrl ?>" style="max-width:100%" />
                                    <button type="button" class="btn btn-secondary btn-sm d-block m-auto mt-2 cfg-copy" data-text="<?= htmlspecialchars(getArrayValue($arrayConfig, "npv_link", ""), ENT_QUOTES) ?>">کپی لینک NPV</button>
                                </div>
                                <div class="cfg-pane" data-t="sub" style="display:none">
                                    <p class="small text-muted">لینک ساب را در برنامه اضافه کنید تا روز و حجم باقی‌مانده را همان‌جا ببینید</p>
                                    <input class="form-control form-control-sm" dir="ltr" readonly value="<?= htmlspecialchars(getArrayValue($arrayConfig, "sub_link", ""), ENT_QUOTES) ?>" onclick="this.select()">
                                    <button type="button" class="btn btn-secondary btn-sm d-block m-auto mt-2 cfg-copy" data-text="<?= htmlspecialchars(getArrayValue($arrayConfig, "sub_link", ""), ENT_QUOTES) ?>">کپی لینک ساب</button>
                                </div>
                                <script>
                                (function () {
                                    var tabs = document.querySelectorAll('.cfg-tab'), panes = document.querySelectorAll('.cfg-pane');
                                    tabs.forEach(function (b) {
                                        b.addEventListener('click', function () {
                                            tabs.forEach(function (x) { x.className = 'btn cfg-tab ' + (x === b ? 'btn-primary' : 'btn-outline-primary'); });
                                            panes.forEach(function (p) { p.style.display = p.getAttribute('data-t') === b.getAttribute('data-t') ? '' : 'none'; });
                                        });
                                    });
                                    document.querySelectorAll('.cfg-copy').forEach(function (b) {
                                        b.addEventListener('click', function () {
                                            var t = b.getAttribute('data-text'), ok = function () { var o = b.textContent; b.textContent = 'کپی شد'; setTimeout(function () { b.textContent = o; }, 1500); };
                                            if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(ok); return; }
                                            var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select();
                                            try { document.execCommand('copy'); ok(); } catch (e) {} document.body.removeChild(a);
                                        });
                                    });
                                })();
                                </script>
                            </div>
                            <div>
                                <?php if (!empty($settings["support_url"])) { ?>
                                    <a target="_blank" class="btn btn-primary d-block" href="<?= $settings["support_url"] ?>">ارتباط با پشتیبانی</a>
                                <?php } ?>
                                <a class="btn btn-danger mt-2 d-block" href="<?= baseUrl("") ?>">خروج</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>