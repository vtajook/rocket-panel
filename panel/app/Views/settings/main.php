<?php
$sshPort        = getArrayValue($settings, "ssh_port", "");
$udpPort        = getArrayValue($settings, "udp_port", "");
$multiuser      = getArrayValue($settings, "multiuser", 0);
$calcTraffic    = getArrayValue($settings, "calc_traffic", 0);
$connectedText  = getArrayValue($settings, "connected_text", "");
$fakeUrl        = getArrayValue($settings, "fake_url", "");
$domainUrl      = getArrayValue($settings, "domain_url", "");


?>

<form id="settings-form" method="post" action="<?= baseUrl("ajax/settings") ?>">
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label class="form-label">پورت SSH</label>
                <input value="<?= $sshPort ?>" type="number" minlength="2" name="ssh_port" class="form-control" placeholder="پورت ssh" required />
            </div>
            <div class="text-body-tertiary mb-1">
                <?= inlineIcon("info-circle") ?>
                <small> در صورت تغییر پورت ssh حتما دستور systemctl restart sshd را اجرا کنید.</small>
            </div>
            <div class="form-group mb-2">
                <label class="form-label">پورت UDP (تماس)</label>
                <input value="<?= $udpPort ?>" type="number" minlength="4" name="udp_port" class="form-control" placeholder="پورت udp" required />
            </div>
            <div class="form-group mb-3">
                <label for="expiry_type" class="form-label">محاسبه ترافیک</label>
                <div class="form-control">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="calc_traffic" value="1" required <?= $calcTraffic  ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">فعال</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="calc_traffic" value="0" required <?= !$calcTraffic  ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">غیر فعال</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group mb-0">
                <label for="expiry_type" class="form-label">چند کاربری <small class="text-body-tertiary">(فعال شدن عملیات کاربران همزمان در افزودن کاربر)</small></label>
                <div class="form-control">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="multiuser" value="1" required <?= $multiuser ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">فعال</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="multiuser" value="0" required <?= !$multiuser ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">غیر فعال</label>
                    </div>
                </div>
            </div>
            <div class="text-body-tertiary mb-1">
                <?= inlineIcon("info-circle") ?>
                <small>در صورت غیر فعال شدن کاربران بدون محدودیت میتوانند متصل شوند.</small>
            </div>

            <div class="form-group mb-0">
                <label class="form-label">متن اتصال</label>
                <input value="<?= $connectedText ?>" name="connected_text" class="form-control" placeholder="یک متن جهت نمایش به کاربر در زمان اتصال وارد کنید" />
            </div>
            <div class="text-body-tertiary mb-1">
                <?= inlineIcon("info-circle") ?>
                <small>متنی که زمان متصل شدن کاربر در نرم افزارهای موبایل نمایش داده میشود.</small>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">آدرس سایت جعلی</label>
                <input value="<?= $fakeUrl ?>" type="url" name="fake_url" class="form-control text-end dir-ltr" placeholder="آدرس سایت جعلی را وارد کنید" />
            </div>
        </div>
        <div class="col-lg-12">
            <div class="form-group mb-0">
                <label class="form-label">ثبت دامنه یا تغییر آی پی </label>
                <input value="<?= $domainUrl ?>" type="url" name="domain_url" class="form-control text-end dir-ltr" placeholder="e.g. http://sub.example.com:panelport" />
            </div>
            <div class="text-body-tertiary mb-1">
                <?= inlineIcon("info-circle") ?>
                <small>
                    این قسمت برای زمان‌هایی است که IP فعلی شما مسدود شده و نیاز به تغییر سرور ندارید. آدرس IP جدید را وارد کرده یا در صورت اتصال دامنه به IP، دامنه و پورت مربوطه را وارد کنید. این اقدام مهم برای محاسبه ترافیک و مصرف کاربران است.
                </small>
            </div>
        </div>
    </div>
    <div class="form-group mt-2 mb-2">
        <button type="submit" class="btn btn-primary btn-float-icon">
            <?= inlineIcon("save") ?>
            ذخیره
        </button>
    </div>

</form>