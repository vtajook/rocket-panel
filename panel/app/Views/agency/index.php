<?php use App\Agency\Agency as Ag; $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }; ?>
<?php include __DIR__ . "/_tabs.php"; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0">نمایندگان (<?= count($list) ?>)</h6>
    <a class="btn btn-primary btn-sm" href="<?= baseUrl('agency/new') ?>"><?= inlineIcon("plus") ?> نماینده جدید</a>
</div>
<div class="card">
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table ag-table mb-0">
                <thead>
                    <tr>
                        <th>نماینده</th>
                        <th>وضعیت</th>
                        <th>موجودی</th>
                        <th>اکانت (فعال / کل)</th>
                        <th>آنلاین</th>
                        <th>فروش ۳۰ روز</th>
                        <th>آخرین فروش</th>
                        <th>آخرین ورود</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$list) { ?>
                        <tr><td colspan="9" class="text-center py-4">هنوز نماینده‌ای ندارید</td></tr>
                    <?php } ?>
                    <?php foreach ($list as $item) { $a = $item['agent']; ?>
                        <tr>
                            <td>
                                <?= str_repeat('<span class="ag-indent">↳</span>', max(0, $item['depth'] - 1)) ?>
                                <a href="<?= baseUrl('agency/agent/' . $a['id']) ?>" class="fw-bold"><?= $e($a['fullname']) ?></a>
                                <div class="ag-hint ag-num"><?= $e($a['username']) ?></div>
                            </td>
                            <td>
                                <?php foreach ($item['badges'] as $badge) { ?>
                                    <span class="badge bg-<?= $badge[1] ?>"><?= $badge[0] ?></span>
                                <?php } ?>
                            </td>
                            <td class="ag-num <?= $item['balance'] < 0 ? 'ag-neg' : '' ?>"><?= number_format($item['balance']) ?></td>
                            <td class="ag-num">
                                <a href="<?= baseUrl('users?owner=' . urlencode($a['username'])) ?>"><?= $item['active'] ?> / <?= $item['total'] ?></a>
                            </td>
                            <td class="ag-num"><?= $item['online'] ?></td>
                            <td class="ag-num"><?= $item['sales']['cnt'] ?> <span class="ag-hint">(<?= number_format($item['sales']['paid']) ?>)</span></td>
                            <td><?= Ag::jdate($item['sales']['last'], true) ?></td>
                            <td><?= Ag::jdate(isset($a['last_login']) ? $a['last_login'] : 0, true) ?></td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= baseUrl('agency/agent/' . $a['id']) ?>">مدیریت</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('agency/activity?agent=' . $a['id']) ?>">کارها</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="ag-hint mt-2">واحد مبلغ‌ها: <?= $e($settings['currency']) ?>. روی تعداد اکانت بزنید تا اکانت‌های همان نماینده را ببینید.</p>
<?php include __DIR__ . "/_js.php"; ?>
