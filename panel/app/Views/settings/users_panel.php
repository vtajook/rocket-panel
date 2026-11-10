<?php
$supportUrl     = getArrayValue($settings, "support_url", "");
$theme          = getArrayValue($settings, "theme", "light");
$welecomText    = getArrayValue($settings, "welecom_text", "");
$logoUrl        = getArrayValue($settings, "logo_url", "");
?>

<form id="settings-form" method="post" action="<?= baseUrl("ajax/settings/users-panel") ?>">
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group mb-3">
                <label class="form-label">لینک پشتیبانی (تلگرام یا واتساپ و...)</label>
                <input value="<?= $supportUrl ?>" type="url" name="support_url" class="form-control text-end dir-ltr" placeholder="https://t.me/rocket_ssh" required />
            </div>
            <div class="form-group mb-3">
                <label for="expiry_type" class="form-label">ظاهر</label>
                <div class="form-control">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="theme" value="light" <?= $theme == "light" ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">روشن</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="theme" value="dark" <?= $theme == "dark" ? "checked" : "" ?>>
                        <label class="form-check-label  mb-0">تیره</label>
                    </div>
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">آدرس تصویر لوگو</label>
                <input value="<?= $logoUrl ?>" type="url" name="logo_url" class="form-control text-end dir-ltr" placeholder="https://raw.githubusercontent.com/mahmoud-ap/rocket-ssh/master/images/logo.png"  />
            </div>
            <div class="text-body-tertiary mb-3">
                <?= inlineIcon("info-circle") ?>
                <small>به منظور نمایش لوگو شخصی در صفحه اطلاعات کاربر، لطفاً تصویر لوگو را در یکی از سایت‌های آپلود فایل آپلود کرده و لینک آن را در اینجا قرار دهید. بهترین ابعاد برای تصویر باید 150x150 پیکسل باشد.</small>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">متن خوش آمد گویی</label>
                <input value="<?= $welecomText ?>" type="text" name="welecom_text" class="form-control" placeholder="یک متن جهت خوش آمد گویی وارد کنید"  />
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