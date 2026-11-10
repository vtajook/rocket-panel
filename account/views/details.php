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
                                <p class="small">جهت استفاده در برنامه Netmod اندروید تصویر زیر را اسکن کنید</p>
                                <img class="m-auto" src="<?= $netmodQrUrl ?>" />
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