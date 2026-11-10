<?php
use App\Agency\Agency as Ag;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$f = function ($k) use ($filters) { return isset($filters[$k]) ? $filters[$k] : ''; };
$csvQuery = $filters; unset($csvQuery['page']); $csvQuery['csv'] = 1;
?>
<?php if ($isAdminView) { include __DIR__ . "/_tabs.php"; } ?>
<form class="row g-2 mb-3" method="get" action="<?= baseUrl($baseLink) ?>">
    <?php if (count($agents) > 1 || $isAdminView) { ?>
    <div class="col-6 col-md-3">
        <select name="agent" class="form-select form-select-sm">
            <option value="">همه نماینده‌ها</option>
            <?php foreach ($agents as $a) { if (!$a) { continue; } ?>
                <option value="<?= $a['id'] ?>" <?= $f('agent') == $a['id'] ? 'selected' : '' ?>><?= $e($a['fullname']) ?></option>
            <?php } ?>
        </select>
    </div>
    <?php } ?>
    <div class="col-6 col-md-3">
        <select name="kind" class="form-select form-select-sm">
            <option value="">همه نوع‌ها</option>
            <?php foreach (Ag::$KIND_LABELS as $key => $label) { ?>
                <option value="<?= $key ?>" <?= $f('kind') == $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-6 col-md-3"><input name="q" class="form-control form-control-sm ag-num" placeholder="نام اکانت" value="<?= $e($f('q')) ?>"></div>
    <div class="col-6 col-md-3 d-flex gap-1">
        <button class="btn btn-sm btn-primary flex-fill">فیلتر</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl($baseLink . '?' . http_build_query($csvQuery)) ?>">خروجی CSV</a>
    </div>
</form>
<div class="card"><div class="card-body p-2">
    <div class="table-responsive">
        <table class="table ag-table mb-0">
            <thead><tr><th>زمان</th><th>نماینده</th><th>نوع</th><th>اکانت</th><th>روز</th><th>مبلغ</th><th>موجودی بعد</th><?php if ($isAdminView) { ?><th>سود بالادست</th><?php } ?><th>توسط</th><th>توضیح</th></tr></thead>
            <tbody>
                <?php foreach ($list['rows'] as $row) { ?>
                    <tr>
                        <td class="text-nowrap"><?= Ag::jdate($row->t, true) ?></td>
                        <td><?= $e(isset($names[$row->agent_id]) ? $names[$row->agent_id] : '#' . $row->agent_id) ?></td>
                        <td><?= isset(Ag::$KIND_LABELS[$row->kind]) ? Ag::$KIND_LABELS[$row->kind] : $e($row->kind) ?></td>
                        <td class="ag-num"><?= $e($row->acct) ?></td>
                        <td class="ag-num"><?= $row->days ? (int) $row->days : '' ?></td>
                        <td class="ag-num <?= $row->amt < 0 ? 'ag-neg' : ($row->amt > 0 ? 'ag-pos' : '') ?>"><?= number_format($row->amt) ?></td>
                        <td class="ag-num"><?= number_format($row->bal) ?></td>
                        <?php if ($isAdminView) { ?><td class="ag-num"><?= $row->profit ? number_format($row->profit) : '' ?></td><?php } ?>
                        <td class="ag-num"><?= $e($row->by_name) ?></td>
                        <td><?= $e($row->note) ?></td>
                    </tr>
                <?php } ?>
                <?php if (!count($list['rows'])) { ?><tr><td colspan="10" class="text-center py-4">ردیفی پیدا نشد</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . "/_pager.php"; ?>
</div></div>
