<?php $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }; ?>
<?php if (!empty($newKey)) { ?>
    <div class="alert alert-warning">کلید جدید (فقط همین یک بار نمایش داده می‌شود، همین حالا ذخیره کنید):<br><code dir="ltr" style="user-select:all"><?= $e($newKey) ?></code></div>
<?php } ?>
<div class="ag-form-section">
    <h6>کلیدهای API</h6>
    <?php if ($canApi) { ?>
    <form class="ag-form row g-2 mb-3" action="<?= baseUrl('agency/my/keys/new') ?>">
        <div class="col-md-4"><input name="name" class="form-control" placeholder="نام کلید (مثلاً ربات)"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">ساخت کلید</button></div>
    </form>
    <?php } else { ?><div class="ag-hint">مدیر دسترسی API را برای شما فعال نکرده است.</div><?php } ?>
    <div class="table-responsive"><table class="table ag-table">
        <thead><tr><th>نام</th><th>پیشوند</th><th>آخرین استفاده</th><th>IP</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) { ?>
            <tr><td><?= $e($r->name) ?></td><td dir="ltr"><?= $e($r->prefix) ?>…</td>
                <td class="ag-num"><?= $r->last_used ? \App\Agency\Agency::jdate($r->last_used) : '—' ?></td><td dir="ltr"><?= $e($r->last_ip) ?></td>
                <td><form class="ag-form d-inline" action="<?= baseUrl('agency/my/keys/' . (int) $r->id . '/delete') ?>" data-confirm="کلید حذف شود؟"><button class="btn btn-sm btn-outline-danger">حذف</button></form></td></tr>
        <?php } ?>
        <?php if (!$rows) { ?><tr><td colspan="5" class="text-center">کلیدی ندارید</td></tr><?php } ?>
        </tbody>
    </table></div>
    <div class="ag-hint mt-2">استفاده: هدر <code>X-API-Key: vl_...</code> یا <code>Authorization: Bearer vl_...</code> روی همان مسیرهای <code>/ajax/users...</code> پنل. IPهای مجاز و سقف درخواست را مدیر تعیین می‌کند.</div>
</div>
<?php include __DIR__ . "/_js.php"; ?>
