<?php
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$labels = ['pending' => ['در انتظار', 'warning'], 'approved' => ['تأیید شد', 'success'], 'rejected' => ['رد شد', 'danger']];
?>
<?php if ($manager) { include __DIR__ . "/_tabs.php"; } else { ?>
<div class="ag-form-section">
    <h6>درخواست انتقال اکانت به نماینده دیگر</h6>
    <form class="ag-form row g-2" action="<?= baseUrl('agency/my/requests/new') ?>">
        <div class="col-md-3"><input name="acct" class="form-control" placeholder="نام کاربری اکانت" required></div>
        <div class="col-md-3"><input name="target" class="form-control" placeholder="نام کاربری نماینده مقصد" required></div>
        <div class="col-md-4"><input name="text" class="form-control" placeholder="توضیح"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">ارسال</button></div>
    </form>
</div>
<?php } ?>
<div class="ag-form-section">
    <h6>درخواست‌ها</h6>
    <div class="table-responsive"><table class="table ag-table">
        <thead><tr><th>زمان</th><?php if ($manager) { ?><th>نماینده</th><?php } ?><th>اکانت</th><th>مقصد / توضیح</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) { $parts = explode('|', $r->note, 2); $st = $labels[$r->status]; ?>
            <tr><td class="ag-num"><?= \App\Agency\Agency::jdate($r->t) ?></td>
                <?php if ($manager) { ?><td><?= $e(isset($names[$r->agent_id]) ? $names[$r->agent_id] : '#' . $r->agent_id) ?></td><?php } ?>
                <td><?= $e($r->acct) ?></td><td><?= $e($parts[0]) ?><?= isset($parts[1]) && $parts[1] !== '' ? ' — ' . $e($parts[1]) : '' ?></td>
                <td><span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span><?= $r->answer ? ' ' . $e($r->answer) : '' ?></td>
                <td class="text-nowrap"><?php if ($manager && $r->status == 'pending') { ?>
                    <form class="ag-form d-inline" action="<?= baseUrl('agency/requests/' . (int) $r->id . '/decide') ?>"><input type="hidden" name="approve" value="1"><button class="btn btn-sm btn-success">تأیید</button></form>
                    <form class="ag-form d-inline" action="<?= baseUrl('agency/requests/' . (int) $r->id . '/decide') ?>"><input type="hidden" name="approve" value="0"><button class="btn btn-sm btn-outline-danger">رد</button></form>
                <?php } ?></td></tr>
        <?php } ?>
        <?php if (!$rows) { ?><tr><td colspan="6" class="text-center">درخواستی نیست</td></tr><?php } ?>
        </tbody>
    </table></div>
</div>
<?php include __DIR__ . "/_js.php"; ?>
