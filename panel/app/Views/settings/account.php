<form id="acc-form" class="row g-3" style="max-width:640px" autocomplete="off">
    <div class="col-12"><h6 class="mb-0">تأیید هویت (مشخصات فعلی)</h6></div>
    <div class="col-md-6">
        <label class="form-label">نام کاربری فعلی</label>
        <input class="form-control" name="current_username" dir="ltr" required autocomplete="off">
    </div>
    <div class="col-md-6">
        <label class="form-label">رمز فعلی</label>
        <input class="form-control" type="password" name="current_password" dir="ltr" required autocomplete="new-password">
    </div>
    <div class="col-12"><hr class="my-1"><h6 class="mb-0">مشخصات جدید (هرکدام را که می‌خواهید عوض کنید)</h6></div>
    <div class="col-md-6">
        <label class="form-label">نام کاربری جدید</label>
        <input class="form-control" name="new_username" dir="ltr" autocomplete="off">
    </div>
    <div class="col-md-6"></div>
    <div class="col-md-6">
        <label class="form-label">رمز جدید (حداقل ۶ حرف)</label>
        <input class="form-control" type="password" name="new_password" dir="ltr" autocomplete="new-password">
    </div>
    <div class="col-md-6">
        <label class="form-label">تکرار رمز جدید</label>
        <input class="form-control" type="password" name="new_password2" dir="ltr" autocomplete="new-password">
    </div>
    <div class="col-12 d-flex gap-2 align-items-center">
        <button type="submit" class="btn btn-primary">ذخیره</button>
        <span id="acc-msg" class="small"></span>
    </div>
</form>
<script>
    $(function() {
        $("#acc-form").on("submit", function(e) {
            e.preventDefault();
            $("#acc-msg").removeClass("text-danger text-success").text("...");
            $.post(baseUrl("ajax/settings/account"), $(this).serialize()).done(function(r) {
                $("#acc-msg").addClass("text-success").text(r.messages);
                $("#acc-form")[0].reset();
            }).fail(function(x) {
                var m = x.responseJSON && x.responseJSON.messages;
                $("#acc-msg").addClass("text-danger").text(typeof m === "string" ? m : (m && m[0]) || "خطا");
            });
        });
    });
</script>
