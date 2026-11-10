<?php
use App\Agency\Agency as Ag;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$f = function ($k) use ($filters) { return isset($filters[$k]) ? $filters[$k] : ''; };
$baseLink = 'agency/activity';
$detailText = function ($detail) {
    if ($detail === null || $detail === '') { return ''; }
    $data = json_decode($detail, true);
    if (!is_array($data)) { return $detail; }
    $labels = ['days' => 'روز', 'devices' => 'کاربر', 'cost' => 'هزینه', 'plan' => 'پلن', 'refund' => 'برگشت', 'amount' => 'مبلغ', 'note' => 'توضیح', 'requested' => 'درخواست', 'created' => 'ساخته‌شده', 'extra_days' => 'روز اضافه', 'extra_devices' => 'کاربر اضافه', 'count' => 'تعداد', 'reason' => 'دلیل'];
    $parts = [];
    foreach ($data as $key => $value) {
        if ($value === null || $value === '') { continue; }
        $parts[] = (isset($labels[$key]) ? $labels[$key] : $key) . ': ' . (is_numeric($value) ? number_format((float) $value) : $value);
    }
    return implode(' ، ', $parts);
};
?>
<?php include __DIR__ . "/_tabs.php"; ?>
<form class="row g-2 mb-3" method="get" action="<?= baseUrl($baseLink) ?>">
    <div class="col-6 col-md-4">
        <select name="agent" class="form-select form-select-sm">
            <option value="">همه نماینده‌ها</option>
            <?php foreach ($agents as $a) { ?>
                <option value="<?= $a['id'] ?>" <?= $f('agent') == $a['id'] ? 'selected' : '' ?>><?= $e($a['fullname']) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-6 col-md-4">
        <select name="action" class="form-select form-select-sm">
            <option value="">همه کارها</option>
            <?php foreach (Ag::$ACTION_LABELS as $key => $label) { ?>
                <option value="<?= $key ?>" <?= $f('action') == $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
</form>
<div class="card"><div class="card-body p-2">
    <div class="table-responsive">
        <table class="table ag-table mb-0">
            <thead><tr><th>زمان</th><th>چه کسی</th><th>چه کاری</th><th>روی</th><th>جزئیات</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($list['rows'] as $row) { ?>
                    <tr>
                        <td class="text-nowrap"><?= Ag::jdate($row->t, true) ?></td>
                        <td class="ag-num"><?= $e($row->actor_name) ?><?= $row->as_admin ? ' <span class="badge bg-warning text-dark">توسط مدیر ' . $e($row->as_admin) . '</span>' : '' ?></td>
                        <td><?= isset(Ag::$ACTION_LABELS[$row->action]) ? Ag::$ACTION_LABELS[$row->action] : $e($row->action) ?></td>
                        <td class="ag-num"><?= $e($row->target) ?></td>
                        <td><?= $e($detailText($row->detail)) ?></td>
                        <td class="ag-num"><?= $e($row->ip) ?></td>
                    </tr>
                <?php } ?>
                <?php if (!count($list['rows'])) { ?><tr><td colspan="6" class="text-center py-4">هنوز کاری ثبت نشده است</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . "/_pager.php"; ?>
</div></div>
