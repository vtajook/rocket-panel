<div class="custome-breadcrumb">
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= baseUrl("dashboard") ?>">داشبورد</a></li>
            <li class="breadcrumb-item active">بروزرسانی پنل</li>
        </ol>
    </nav>
</div>
<div class="card">
    <div class="card-body">
        <p>نسخه نصب‌شده: <strong dir="ltr" id="cur-ver"><?= htmlspecialchars($current) ?></strong></p>
        <p>آخرین نسخه در گیت‌هاب: <strong dir="ltr"><?= $remote !== '' ? htmlspecialchars($remote) : "نامشخص" ?></strong></p>
        <?php if ($hasUpdate) { ?>
            <div class="alert alert-info">نسخه جدید موجود است.</div>
        <?php } elseif ($remote === '') { ?>
            <div class="alert alert-warning">نسخه گیت‌هاب خوانده نشد (اینترنت سرور یا گیت‌هاب). می‌توانید بروزرسانی را امتحان کنید.</div>
        <?php } else { ?>
            <div class="alert alert-success">✅ آخرین نسخه نصب است؛ نیازی به بروزرسانی نیست.</div>
        <?php } ?>
        <p class="text-body-secondary small">
            فقط فایل‌های پنل جایگزین می‌شوند. کاربران، تنظیمات، دیتابیس و اتصال‌های SSH دست نمی‌خورند و هیچ کاربری قطع نمی‌شود.
        </p>
        <?php if ($hasUpdate || $remote === '') { ?>
            <button class="btn btn-primary" id="btn-run-update" <?= $toolReady ? "" : "disabled" ?>>بروزرسانی پنل</button>
        <?php } ?>
        <?php if (!$toolReady) { ?>
            <div class="text-danger small mt-2">ابزار بروزرسانی نصب نیست؛ یک بار دستور نصب (install.sh) را اجرا کنید و گزینه نصب/بروزرسانی را بزنید.</div>
        <?php } ?>
        <pre id="update-log" class="mt-3 p-2 border rounded small" dir="ltr" style="display:none;max-height:300px;overflow:auto;white-space:pre-wrap"></pre>
    </div>
</div>
<script>
    $(function() {
        var timer = null;
        function poll() {
            $.get(baseUrl("ajax/update/log")).done(function(r) {
                $("#update-log").show().text(r.log).scrollTop(99999);
                if (r.done) {
                    clearInterval(timer);
                    $("#btn-run-update").prop("disabled", false);
                    if (r.ok) { setTimeout(function() { location.reload(); }, 1500); }
                }
            });
        }
        $("#btn-run-update").on("click", function() {
            if (!confirm("پنل به آخرین نسخه بروزرسانی شود؟")) { return; }
            var btn = $(this).prop("disabled", true);
            $.post(baseUrl("ajax/update/run")).done(function() {
                timer = setInterval(poll, 2000); poll();
            }).fail(function(x) {
                btn.prop("disabled", false);
                alert(x.responseJSON && x.responseJSON.messages ? x.responseJSON.messages[0] : "خطا");
            });
        });
    });
</script>
