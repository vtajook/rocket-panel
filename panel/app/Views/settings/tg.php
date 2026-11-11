<?php $t = isset($tg) ? $tg : []; $v = function ($k, $d = '') use ($t) { return isset($t[$k]) ? htmlspecialchars($t[$k]) : $d; }; ?>
<form id="tg-form" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">توکن ربات (از BotFather)</label>
        <input class="form-control" name="tgb_token" dir="ltr" placeholder="123456789:AAH..." value="<?= $v('tgb_token') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">آیدی عددی تلگرام (چند تا با کاما)</label>
        <input class="form-control" name="tgb_chat" dir="ltr" placeholder="123456789" value="<?= $v('tgb_chat') ?>">
        <div class="form-text">آیدی عددی خودتان را از ربات‌هایی مثل userinfobot بگیرید و ربات خودتان را یک بار Start کنید.</div>
    </div>

    <div class="col-12"><hr class="my-1"></div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="tgb_backup" value="1" id="f-bk" <?= $v('tgb_backup') ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-bk">بکاپ خودکار دیتابیس به تلگرام</label>
        </div>
        <label class="form-label">هر چند دقیقه؟ (۱ تا ۱۴۴۰ = ۲۴ ساعت)</label>
        <input class="form-control" type="number" min="1" max="1440" name="tgb_interval" value="<?= $v('tgb_interval', 60) ?>">
        <div class="form-text">فایل بکاپ همراه آی‌پی سرور، تعداد کاربران و زمان ارسال می‌شود.</div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="tgb_usage" value="1" id="f-us" <?= $v('tgb_usage') ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-us">هشدار مصرف حجم</label>
        </div>
        <label class="form-label">وقتی مصرف به این درصد رسید</label>
        <input class="form-control" type="number" min="1" max="100" name="tgb_usage_pct" value="<?= $v('tgb_usage_pct', 80) ?>">
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="tgb_expiry" value="1" id="f-ex" <?= $v('tgb_expiry') ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-ex">هشدار نزدیک شدن انقضا</label>
        </div>
        <label class="form-label">چند روز مانده به انقضا</label>
        <input class="form-control" type="number" min="1" max="30" name="tgb_expiry_days" value="<?= $v('tgb_expiry_days', 3) ?>">
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="tgb_login" value="1" id="f-lg" <?= $v('tgb_login') ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-lg">هشدار ورود به خود پنل (مدیر و نماینده؛ شامل تلاش ناموفق، همراه آی‌پی)</label>
        </div>
        <div class="form-text">برای تشخیص هک: هر ورود موفق و هر رمز اشتباه گزارش می‌شود (رمز اشتباه حداکثر دقیقه‌ای یک بار از هر آی‌پی).</div>
    </div>

    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <button type="button" class="btn btn-outline-secondary" id="tg-test">پیام آزمایشی</button>
        <button type="button" class="btn btn-outline-secondary" id="tg-bk">ارسال بکاپ همین الان</button>
        <span id="tg-msg" class="small"></span>
    </div>
</form>
<script>
    $(function() {
        function done(r) {
            var m = r && r.responseJSON ? r.responseJSON.messages : (r && r.messages);
            $("#tg-msg").text(typeof m === "string" ? m : "انجام شد");
        }
        $("#tg-form").on("submit", function(e) {
            e.preventDefault();
            $("#tg-msg").text("...");
            $.post(baseUrl("ajax/settings/tg"), $(this).serialize()).done(done).fail(done);
        });
        $("#tg-test, #tg-bk").on("click", function() {
            var what = this.id === "tg-bk" ? "backup" : "message";
            $("#tg-msg").text("در حال ارسال...");
            $.post(baseUrl("ajax/settings/tg-test"), {what: what}).done(done).fail(done);
        });
    });
</script>
