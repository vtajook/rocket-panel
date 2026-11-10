<?php $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }; include __DIR__ . "/_tabs.php"; ?>
<div class="ag-form-section"><h6>لاگ API (۳۰۰ مورد آخر)</h6>
<div class="table-responsive"><table class="table ag-table">
    <thead><tr><th>زمان</th><th>نماینده</th><th>IP</th><th>متد</th><th>مسیر</th><th>نتیجه</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r) { ?>
        <tr><td class="ag-num"><?= \App\Agency\Agency::jdate($r->t) ?></td><td><?= $e(isset($names[$r->agent_id]) ? $names[$r->agent_id] : '#' . $r->agent_id) ?></td>
            <td dir="ltr"><?= $e($r->ip) ?></td><td><?= $e($r->method) ?></td><td dir="ltr"><?= $e($r->path) ?></td>
            <td><span class="badge bg-<?= $r->status == 200 ? 'success' : 'danger' ?>"><?= (int) $r->status ?></span></td></tr>
    <?php } ?>
    <?php if (!$rows) { ?><tr><td colspan="6" class="text-center">موردی نیست</td></tr><?php } ?>
    </tbody>
</table></div></div>
