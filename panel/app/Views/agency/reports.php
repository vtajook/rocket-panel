<?php
use App\Agency\Agency as Ag;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$totalPaid = 0; $totalRefund = 0; $totalSales = 0; $totalRenew = 0;
foreach ($report['perAgent'] as $row) { $totalPaid += $row['paid']; $totalRefund += $row['refund']; $totalSales += $row['sale']; $totalRenew += $row['renew']; }
$wallets = 0; foreach ($balances as $b) { $wallets += $b; }
?>
<?php include __DIR__ . "/_tabs.php"; ?>
<div class="d-flex gap-1 mb-3 flex-wrap">
    <?php foreach ([1 => 'امروز', 7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز', 365 => 'یک سال'] as $d => $label) { ?>
        <a class="btn btn-sm <?= $report['days'] == $d ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= baseUrl('agency/reports?days=' . $d) ?>"><?= $label ?></a>
    <?php } ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('agency/reports?csv=1&days=' . $report['days']) ?>">خروجی CSV</a>
</div>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">فروش جدید</div><div class="ag-stat-value ag-num"><?= number_format($totalSales) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">تمدید</div><div class="ag-stat-value ag-num"><?= number_format($totalRenew) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">خالص دریافتی از نماینده‌ها</div><div class="ag-stat-value ag-num"><?= number_format($totalPaid - $totalRefund) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">مجموع موجودی کیف پول‌ها</div><div class="ag-stat-value ag-num"><?= number_format($wallets) ?></div></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="ag-form-section">
            <h6>به تفکیک نماینده</h6>
            <div class="table-responsive"><table class="table ag-table mb-0">
                <thead><tr><th>نماینده</th><th>فروش</th><th>تمدید</th><th>افزایش</th><th>پرداختی</th><th>برگشتی</th><th>خالص</th></tr></thead>
                <tbody>
                    <?php foreach ($report['perAgent'] as $id => $row) { ?>
                        <tr>
                            <td><a href="<?= baseUrl('agency/agent/' . $id) ?>"><?= $e(isset($names[$id]) ? $names[$id] : '#' . $id) ?></a></td>
                            <td class="ag-num"><?= $row['sale'] - (isset($report['reversed'][$id]) ? 0 : 0) ?></td>
                            <td class="ag-num"><?= $row['renew'] ?></td>
                            <td class="ag-num"><?= $row['other'] ?></td>
                            <td class="ag-num"><?= number_format($row['paid']) ?></td>
                            <td class="ag-num"><?= number_format($row['refund']) ?></td>
                            <td class="ag-num fw-bold"><?= number_format($row['paid'] - $row['refund']) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$report['perAgent']) { ?><tr><td colspan="7" class="text-center py-3">در این بازه فروشی ثبت نشده است</td></tr><?php } ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ag-form-section">
            <h6>روز به روز</h6>
            <div class="table-responsive"><table class="table ag-table mb-0">
                <thead><tr><th>تاریخ</th><th>فروش + تمدید</th><th>خالص</th></tr></thead>
                <tbody>
                    <?php foreach ($report['daily'] as $day => $row) { ?>
                        <tr><td class="ag-num"><?= $day ?></td><td class="ag-num"><?= $row['count'] ?></td><td class="ag-num"><?= number_format($row['net']) ?></td></tr>
                    <?php } ?>
                    <?php if (!$report['daily']) { ?><tr><td colspan="3" class="text-center py-3">—</td></tr><?php } ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<p class="ag-hint">فروش‌هایی که خودِ مدیر انجام می‌دهد (از پنل یا ربات) در این گزارش نیست؛ این صفحه فقط کار نماینده‌ها را نشان می‌دهد.</p>
