<?php
use App\Agency\Agency as Ag;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$credit = (int) Ag::limit($me, 'credit_limit');
?>
<?php if ($sellBlock !== '') { ?><div class="alert alert-warning"><?= $e($sellBlock) ?></div><?php } ?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">موجودی کیف پول</div><div class="ag-stat-value ag-num <?= $balance < 0 ? 'ag-neg' : '' ?>"><?= number_format($balance) ?> <small><?= $e($settings['currency']) ?></small></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">اکانت فعال / کل</div><div class="ag-stat-value ag-num"><?= $activeAccounts ?> / <?= $accounts ?></div></div></div></div>
    <?php if ($credit > 0) { ?><div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">اعتبار (فروش نسیه)</div><div class="ag-stat-value ag-num"><?= number_format($credit) ?></div></div></div></div><?php } ?>
    <?php if ($me['expiry']) { ?><div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">پایان مهلت نمایندگی</div><div class="ag-stat-value" style="font-size:14px"><?= Ag::jdate($me['expiry']) ?></div></div></div></div><?php } ?>
</div>
<div class="mb-3"><a class="btn btn-sm btn-outline-primary" href="<?= baseUrl('agency/my/ledger') ?>">گردش حساب من</a></div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="ag-form-section">
            <h6>قیمت‌های من</h6>
            <?php if ($plans) { ?>
                <div class="table-responsive"><table class="table ag-table mb-2">
                    <thead><tr><th>پلن</th><th>روز</th><th>کاربر</th><th>قیمت خرید</th><th>قیمت تمدید</th></tr></thead>
                    <tbody>
                        <?php foreach ($plans as $plan) { ?>
                            <tr><td><?= $e($plan['name']) ?></td><td class="ag-num"><?= (int) $plan['days'] ?></td><td class="ag-num"><?= (int) $plan['devices'] ?></td><td class="ag-num"><?= number_format($plan['my_sell']) ?></td><td class="ag-num"><?= number_format($plan['my_renew']) ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table></div>
            <?php } ?>
            <?php if (Ag::can($me, 'custom')) { ?>
                <div>فروش سفارشی: هر روز <b class="ag-num"><?= number_format($perDay) ?></b><?= $perGb > 0 ? ' — هر گیگ <b class="ag-num">' . number_format($perGb) . '</b>' : '' ?> <?= $e($settings['currency']) ?><?= Ag::discountOf($me) > 0 ? ' (با ' . Ag::discountOf($me) . '٪ تخفیف شما)' : '' ?></div>
            <?php } else { ?>
                <div class="ag-hint">شما فقط با پلن‌های بالا می‌توانید بفروشید.</div>
            <?php } ?>
        </div>
        <div class="ag-form-section">
            <h6>دسترسی‌ها و سقف‌های من</h6>
            <div class="mb-2">
                <?php foreach (Ag::$PERM_LABELS as $key => $label) { if ($key === 'api') { continue; } ?>
                    <span class="badge bg-<?= Ag::can($me, $key) ? 'success' : 'secondary' ?> mb-1"><?= $label ?></span>
                <?php } ?>
            </div>
            <?php foreach (Ag::$LIMIT_LABELS as $key => $label) { $v = Ag::limit($me, $key); if ($v > 0 && $key !== 'credit_limit') { ?>
                <div class="small"><?= $label ?>: <b class="ag-num"><?= number_format($v) ?></b></div>
            <?php } } ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ag-form-section">
            <h6>تغییر رمز ورود</h6>
            <form class="ag-form" action="<?= baseUrl('agency/my/password') ?>" autocomplete="off">
                <input name="current" type="password" class="form-control form-control-sm mb-2" placeholder="رمز فعلی" required>
                <input name="password" type="password" class="form-control form-control-sm mb-2" placeholder="رمز جدید (حداقل ۶ حرف)" required autocomplete="new-password">
                <button class="btn btn-sm btn-primary">تغییر رمز</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . "/_js.php"; ?>
