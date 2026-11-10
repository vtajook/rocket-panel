<div class="modal-dialog modal-lg modal-dialog-scrollable">

    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">
                افزودن کاربر گروهی
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <form id="bulk-users-form" method="post" action="<?= baseUrl("ajax/users/bulk/create") ?>">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="form-group mb-2">
                            <label for="users_count" class="form-label">تعداد کاربران</label>
                            <input type="number" min="1" max="100" name="users_count" class="form-control" placeholder="تعداد کاربرانی که می خواهید ساخته شوند را وارد کنید" required>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label for="prefix_username" class="form-label">عبارت پیشوند نام کاربری</label>
                            <input type="text" name="prefix_username" class="form-control" placeholder="یک عبارت پیشوند وارد کنید مثلا rocket" required>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label for="username_start_num" class="form-label">شروع اعداد نام کاربری</label>
                            <input type="number" min="1" name="username_start_num" class="form-control" placeholder="یک عدد شروع وارد کنید مثلا 100" required>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label for="password_type" class="form-label">نوع رمز عبور</label>
                            <select class="form-select" name="password_type">
                                <option value="number">فقط عدد</option>
                                <option value="letter">فقط حرف</option>
                                <option value="number_letter">ترکیب عدد و حروف</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label for="password_len" class="form-label">طول رمز عبور</label>
                            <input type="number" name="password_len" min="4" max="8" value="4" class="form-control" placeholder="طول رمز عبور را وارد کنید" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label for="limit_users" class="form-label">تعداد کاربران همزمان</label>
                            <input type="number" min="1" name="limit_users" class="form-control" placeholder="تعداد کاربر را وارد کنید" required>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-2">
                            <label fotn class="form-label">مقدار ترافیک (0 نامحدود)</label>
                            <div class="input-group">
                                <input type="number" name="traffic" min="0" class="form-control" placeholder="مقدار ترافیک قابل مصرف را وارد کنید" required>
                                <span class="input-group-text">گیگابایت</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-2">
                        <div class="form-group mb-3">
                            <label for="expiry_type" class="form-label">زمان انقضاء</label>
                            <div class="form-control">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="expiry_type" value="days" checked required>
                                    <label class="form-check-label  mb-0"> براساس روز (از اولین اتصال)</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="expiry_type" value="date" required>
                                    <label class="form-check-label  mb-0">بر اساس تاریخ</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-2" id="expiry-by-days">
                        <div class="mb-2">
                            <div class="form-group mb-0">
                                <label for="exp_days" class="form-label">زمان انقضاء (از اولین اتصال)</label>
                                <div class="input-group">
                                    <input type="number" min="1" name="exp_days" value="" class="form-control" placeholder="تعداد روز" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-2" id="expiry-by-date" style="display:none">
                        <div class="form-group mb-0">
                            <label for="exp_date" class="form-label">تاریخ انقضاء</label>
                            <input type="text" class="form-control datepicker" name="exp_date" value="" placeholder="تاریخ را انتخاب کنید" required>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label fotn class="form-label">توضیحات</label>
                    <textarea name="desc" class="form-control" placeholder="متن توضیحات را وارد کنید"></textarea>
                </div>
            </form>
        </div>
        <div class=" modal-footer">
            <button class="btn btn-primary btn-float-icon" id="btn-submit-bulk-users" form="bulk-users-form" type="submit">
                <?= inlineIcon("save") ?>
                ذخیره
            </button>
        </div>
    </div>
</div>

<script>
    window.initBulkForm()
</script>