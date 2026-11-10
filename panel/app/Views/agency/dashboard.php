<?php
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$r = $reseller;
$card = function ($title, $value, $cls = '') {
    return '<div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">' . $title . '</div><div class="ag-stat-value ag-num ' . $cls . '">' . $value . '</div></div></div></div>';
};
$money = function ($v) use ($r) { return number_format($v) . ' <small>' . htmlspecialchars($r['currency']) . '</small>'; };
?>
<div class="row g-2 mb-3">
    <?= $card('موجودی کیف پول', $money($r['balance']), $r['balance'] < 0 ? 'ag-neg' : '') ?>
    <?= $card('اکانت فعال / کل', $r['active'] . ' / ' . $r['total']) ?>
    <?= $card('آنلاین الان', $r['online']) ?>
    <?= $card('منقضی‌شده', $r['expired']) ?>
    <?= $card('در آستانه انقضا (۳ روز)', $r['soon']) ?>
    <?= $card('فروش امروز', $r['today']['n'] . ' — ' . $money($r['today']['sum'])) ?>
    <?= $card('فروش ۷ روز', $r['week']['n'] . ' — ' . $money($r['week']['sum'])) ?>
    <?= $card('فروش ۳۰ روز', $r['month']['n'] . ' — ' . $money($r['month']['sum'])) ?>
</div>
<div class="ag-form-section">
    <h6>آخرین تراکنش‌ها</h6>
    <div class="table-responsive"><table class="table ag-table">
        <thead><tr><th>زمان</th><th>نوع</th><th>اکانت</th><th>مبلغ</th></tr></thead>
        <tbody>
        <?php foreach ($r['recent'] as $row) { ?>
            <tr><td class="ag-num"><?= \App\Agency\Agency::jdate($row->t, true) ?></td>
                <td><?= $e(isset(\App\Agency\Agency::$KIND_LABELS[$row->kind]) ? \App\Agency\Agency::$KIND_LABELS[$row->kind] : $row->kind) ?></td>
                <td><?= $e($row->acct) ?></td><td class="ag-num <?= $row->amt < 0 ? 'ag-neg' : '' ?>"><?= number_format($row->amt) ?></td></tr>
        <?php } ?>
        <?php if (!$r['recent']) { ?><tr><td colspan="4" class="text-center">هنوز تراکنشی نیست</td></tr><?php } ?>
        </tbody>
    </table></div>
    <a class="btn btn-sm btn-outline-primary" href="<?= baseUrl('agency/my/ledger') ?>">همه گردش حساب</a>
    <a class="btn btn-sm btn-primary" href="<?= baseUrl('users') ?>">اکانت‌ها</a>
</div>
