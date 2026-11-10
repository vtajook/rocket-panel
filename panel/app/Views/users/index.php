<style>
    .btn-items .btn-icon {
        width: 32px !important;
        height: 32px !important;
    }
    .btn-items .btn-icon .icon {
      font-size: 14px !important;
    }
</style>
<div class="custome-breadcrumb has-actions">
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= baseUrl("dashboard") ?>">داشبورد</a></li>
            <li class="breadcrumb-item"><a href="<?= baseUrl("users") ?>">کاربران</a></li>
            <li class="breadcrumb-item active">لیست</li>
        </ol>
    </nav>
    <div class="actions">
        <button class="btn btn-primary btn-ajax-views btn-float-icon" data-url="users/add">
            <?= inlineIcon("add", "icon") ?>
            کاربر تکی
        </button>
        <button class="btn btn-success btn-ajax-views btn-float-icon" data-url="users/bulk-add">
            <?= inlineIcon("add", "icon") ?>
            کاربر گروهی
        </button>
        <button class="btn btn-danger btn-float-icon" style="display: none;" id="btn-bulk-delete">
            <?= inlineIcon("trash", "icon") ?>
            حذف گروهی
        </button>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <form id="filtering-form">
            <div class="row">
                <div class="col-lg-2">
                    <div class="form-group mb-1">
                        <label>جستجو</label>
                        <input type="search" name="main_search" class="form-control" placeholder="جستجو..." />
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="form-group mb-1">
                        <label>وضعیت کاربران</label>
                        <select name="status" class="form-select">
                            <option value="">همه</option>
                            <option value="active">فعال</option>
                            <option value="de_active">غیر فعال</option>
                            <option value="expiry_date">انقضای تاریخ</option>
                            <option value="expiry_traffic">انقضای ترافیک</option>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="form-group mb-1">
                        <label>تعداد کاربر</label>
                        <select name="limit_users" class="form-select">
                            <option value="">همه</option>
                            <option value="single">تک کاربری</option>
                            <option value="multi">چند کاربری</option>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="form-group mb-1">
                        <label> وضعیت اتصال</label>
                        <select name="conn_type" class="form-select">
                            <option value="">همه</option>
                            <option value="online">فقط آنلاین ها</option>
                            <option value="offline">فقط آفلاین ها</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="card-datatable table-responsive">
        <table id="users-table" class="table" style="width: 100%;">
            <tbody>

            </tbody>
        </table>
    </div>
</div>

<div class="modal" tabindex="-1" id="renewal-users-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تمدید کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="renewal-users-form" method="put">
                    <div class="form-group">
                        <label>تعداد روزهای تمدید</label>
                        <input type="number" name="renewal_days" class="form-control" min="1" placeholder="تعداد روزهای تمدید را وارد کنید" required>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label>ثبت از امروز</label>
                                <div class="form-control">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="renewal_date" value="yes" required>
                                        <label class="form-check-label  mb-0">بلی</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" checked name="renewal_date" value="no" required>
                                        <label class="form-check-label  mb-0">خیر</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label>ریست کردن ترافیک</label>
                                <div class="form-control">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="renewal_traffic" value="yes" required>
                                        <label class="form-check-label  mb-0">بلی</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" checked name="renewal_traffic" value="no" required>
                                        <label class="form-check-label  mb-0">خیر</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="btn-submit-renewal" form='renewal-users-form'>
                    <?= inlineIcon("save") ?>
                    ذخیره
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var activeStatus = "<?= $activeStatus ?>";
    var tablePageLength = 25;
</script>