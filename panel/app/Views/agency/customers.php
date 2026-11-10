<?php $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }; ?>
<div class="ag-form-section">
    <h6>مشتری جدید / ویرایش</h6>
    <form class="ag-form row g-2" action="<?= baseUrl('agency/my/customers/save') ?>">
        <input type="hidden" name="id" id="cu-id" value="0">
        <div class="col-md-3"><input name="name" id="cu-name" class="form-control" placeholder="نام" required></div>
        <div class="col-md-2"><input name="phone" id="cu-phone" class="form-control" placeholder="موبایل"></div>
        <div class="col-md-2"><input name="tg_id" id="cu-tg" class="form-control" placeholder="آیدی تلگرام"></div>
        <div class="col-md-3"><input name="note" id="cu-note" class="form-control" placeholder="یادداشت"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">ذخیره</button></div>
    </form>
</div>
<div class="ag-form-section">
    <h6>مشتریان من</h6>
    <div class="table-responsive"><table class="table ag-table">
        <thead><tr><th>نام</th><th>موبایل</th><th>تلگرام</th><th>یادداشت</th><th>اکانت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) { ?>
            <tr><td><?= $e($r->name) ?></td><td><?= $e($r->phone) ?></td><td><?= $e($r->tg_id) ?></td><td><?= $e($r->note) ?></td>
                <td class="ag-num"><?= (int) (isset($counts[$r->id]) ? $counts[$r->id] : 0) ?></td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='(function(r){document.getElementById("cu-id").value=r.id;document.getElementById("cu-name").value=r.name;document.getElementById("cu-phone").value=r.phone;document.getElementById("cu-tg").value=r.tg_id;document.getElementById("cu-note").value=r.note;window.scrollTo(0,0)})(<?= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>ویرایش</button>
                    <form class="ag-form d-inline" action="<?= baseUrl('agency/my/customers/' . (int) $r->id . '/delete') ?>" data-confirm="مشتری حذف شود؟ (اکانت‌هایش می‌مانند)"><button class="btn btn-sm btn-outline-danger">حذف</button></form>
                </td></tr>
        <?php } ?>
        <?php if (!$rows) { ?><tr><td colspan="6" class="text-center">مشتری‌ای ثبت نشده</td></tr><?php } ?>
        </tbody>
    </table></div>
</div>
<div class="ag-form-section">
    <h6>اختصاص اکانت به مشتری</h6>
    <form class="ag-form row g-2" action="<?= baseUrl('agency/my/accounts/customer') ?>">
        <div class="col-md-4"><input name="username" class="form-control" placeholder="نام کاربری اکانت" required></div>
        <div class="col-md-4"><select name="customer_id" class="form-select"><option value="0">بدون مشتری</option>
            <?php foreach ($rows as $r) { ?><option value="<?= (int) $r->id ?>"><?= $e($r->name) ?></option><?php } ?></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">اعمال</button></div>
    </form>
</div>
<?php include __DIR__ . "/_js.php"; ?>
