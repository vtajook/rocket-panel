<div class="custome-breadcrumb has-actions">
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= baseUrl("dashboard") ?>">داشبورد</a></li>
            <li class="breadcrumb-item active">وضعیت فیلترینگ</li>
        </ol>
    </nav>
</div>

<div class="card">
    <div class="card-header text-info">
        <div>
            <?= inlineIcon("info-circle") ?>
            در صورتی که بقیه کشور ها آنلاین باشند و ایران فیلتر باشد به معنای فیلتر شدن سرور ها در ایران میباشد .
        </div>
    </div>
    <div class="card-body py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div id="result-filtering">

                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-3">لطفا تا بارگذاری کامل صبر کنید</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
