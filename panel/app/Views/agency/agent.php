<?php
use App\Agency\Agency as Ag;
$e      = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$isNew  = empty($agent);
$perms  = $isNew ? Ag::defaultPerms() : $agent['perms'];
$limits = $isNew ? [] : $agent['limits'];
$price  = $isNew ? ['plans' => []] : $agent['pricing'];
$val    = function ($arr, $key) { return (isset($arr[$key]) && $arr[$key] !== null && $arr[$key] != 0) ? rtrim(rtrim(number_format((float) $arr[$key], 2, '.', ''), '0'), '.') : ''; };
$pval   = function ($arr, $key) { return (isset($arr[$key]) && $arr[$key] !== null && $arr[$key] !== '') ? rtrim(rtrim(number_format((float) $arr[$key], 2, '.', ''), '0'), '.') : ''; };
$saveUrl = baseUrl('agency/save' . ($isNew ? '' : '/' . $agent['id']));
?>
<?php include __DIR__ . "/_tabs.php"; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h6 class="mb-0">
        <a href="<?= baseUrl('agency') ?>" class="btn btn-sm btn-outline-secondary"><?= inlineIcon("arrow-right") ?></a>
        <?= $isNew ? 'نماینده جدید' : $e($agent['fullname']) . ' <span class="ag-hint ag-num">' . $e($agent['username']) . '</span>' ?>
        <?php if (!$isNew) { foreach ($badges as $badge) { ?><span class="badge bg-<?= $badge[1] ?>"><?= $badge[0] ?></span> <?php } } ?>
    </h6>
    <?php if (!$isNew) { ?>
        <div class="d-flex gap-1 flex-wrap">
            <button class="btn btn-sm btn-outline-primary" onclick="agPost('<?= baseUrl('agency/agent/' . $agent['id'] . '/login') ?>')">ورود به پنل این نماینده</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('users?owner=' . urlencode($agent['username'])) ?>">اکانت‌ها</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('agency/ledger?agent=' . $agent['id']) ?>">گردش حساب</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('agency/activity?agent=' . $agent['id']) ?>">کارها</a>
        </div>
    <?php } ?>
</div>

<?php if (!$isNew) { ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">موجودی کیف پول</div><div class="ag-stat-value ag-num <?= $balance < 0 ? 'ag-neg' : '' ?>"><?= number_format($balance) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">اکانت فعال / کل</div><div class="ag-stat-value ag-num"><?= $activeAccounts ?> / <?= $accounts ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">آخرین ورود</div><div class="ag-stat-value" style="font-size:14px"><?= Ag::jdate($agent['last_login'], true) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card ag-stat"><div class="card-body p-2"><div class="ag-stat-title">عضویت</div><div class="ag-stat-value" style="font-size:14px"><?= Ag::jdate($agent['ctime']) ?></div></div></div></div>
    </div>

    <div class="row g-3 mb-1">
        <div class="col-lg-6">
            <div class="ag-form-section">
                <h6>کیف پول</h6>
                <form class="ag-form row g-2" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/wallet') ?>">
                    <div class="col-4">
                        <select name="kind" class="form-select form-select-sm">
                            <option value="credit">شارژ (+)</option>
                            <option value="debit">کسر (−)</option>
                        </select>
                    </div>
                    <div class="col-8"><input name="amount" class="form-control form-control-sm ag-num" inputmode="numeric" placeholder="مبلغ (<?= $e($settings['currency']) ?>)" required></div>
                    <div class="col-8"><input name="note" class="form-control form-control-sm" placeholder="توضیح (اختیاری)"></div>
                    <div class="col-4"><button class="btn btn-sm btn-primary w-100">ثبت</button></div>
                </form>
                <div class="ag-hint mt-2">هر شارژ یا کسر یک ردیف جدید در دفتر مالی است و قابل پاک کردن نیست؛ اشتباه را با ردیف مخالف اصلاح کنید.</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ag-form-section">
                <h6>مسدود کردن</h6>
                <?php if (Ag::isBanned($agent)) { ?>
                    <p class="mb-2">این نماینده مسدود است<?= $agent['ban_until'] > 1 ? ' تا ' . Ag::jdate($agent['ban_until'], true) : ' (دائمی)' ?><?= $agent['ban_reason'] ? ' — ' . $e($agent['ban_reason']) : '' ?></p>
                    <form class="ag-form" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/ban') ?>">
                        <input type="hidden" name="mode" value="unban">
                        <button class="btn btn-sm btn-success">رفع مسدودی</button>
                    </form>
                <?php } else { ?>
                    <form class="ag-form row g-2" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/ban') ?>" data-confirm="این نماینده و همه زیرنماینده‌هایش دیگر نمی‌توانند وارد پنل شوند. ادامه می‌دهید؟">
                        <div class="col-4"><input name="days" class="form-control form-control-sm ag-num" inputmode="numeric" placeholder="چند روز"></div>
                        <div class="col-8"><input name="reason" class="form-control form-control-sm" placeholder="دلیل (به نماینده نشان داده می‌شود)"></div>
                        <div class="col-12"><button class="btn btn-sm btn-outline-danger">مسدود کن</button> <span class="ag-hint">روز را خالی بگذارید = دائمی. اکانت‌های مشتری‌ها قطع نمی‌شوند.</span></div>
                    </form>
                <?php } ?>
            </div>
            <div class="ag-form-section">
                <h6>توقف اکانت‌ها</h6>
                <form class="ag-form" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/freeze') ?>" data-confirm="<?= !empty($agent['frozen']) ? 'اکانت‌ها دوباره فعال شوند؟' : 'اتصال همه اکانت‌های این نماینده و زیرنماینده‌هایش قطع می‌شود و تا رفع توقف نمی‌توانند وصل شوند. ادامه می‌دهید؟' ?>">
                    <input type="hidden" name="mode" value="<?= !empty($agent['frozen']) ? 'unfreeze' : 'freeze' ?>">
                    <?php if (!empty($agent['frozen'])) { ?><p class="mb-2">اکانت‌های این نماینده الان متوقف است.</p><?php } ?>
                    <button class="btn btn-sm btn-<?= !empty($agent['frozen']) ? 'success' : 'outline-warning' ?>"><?= !empty($agent['frozen']) ? 'رفع توقف اکانت‌ها' : 'توقف همه اکانت‌ها' ?></button>
                    <span class="ag-hint">خود اکانت‌ها پاک یا غیرفعال نمی‌شوند؛ فقط وصل شدنشان بسته می‌شود.</span>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<form class="ag-form" action="<?= $saveUrl ?>" autocomplete="off">
    <div class="ag-form-section">
        <h6>مشخصات</h6>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">نام کاربری ورود</label>
                <input name="username" class="form-control ag-num" value="<?= $isNew ? '' : $e($agent['username']) ?>" <?= $isNew ? 'required' : 'disabled' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label">نام نماینده</label>
                <input name="fullname" class="form-control" value="<?= $isNew ? '' : $e($agent['fullname']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">رمز عبور <?= $isNew ? '' : '<span class="ag-hint">(خالی = بدون تغییر)</span>' ?></label>
                <input name="password" type="text" class="form-control ag-num" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">وضعیت</label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= ($isNew || $agent['is_active']) ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= (!$isNew && !$agent['is_active']) ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">نماینده بالادست</label>
                <select name="parent_id" class="form-select">
                    <option value="0">— مستقیم زیر نظر مدیر —</option>
                    <?php foreach ($parents as $p) { if (!$isNew && Ag::inSubtree($agent['id'], $p['id'])) { continue; } ?>
                        <option value="<?= $p['id'] ?>" <?= (!$isNew && $agent['parent_id'] == $p['id']) ? 'selected' : '' ?>><?= $e($p['fullname']) ?> (<?= $e($p['username']) ?>)</option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">پایان مهلت نمایندگی</label>
                <input name="expiry" class="form-control ag-num" placeholder="مثلاً 1405/12/29 — خالی = بدون مهلت" value="<?= (!$isNew && $agent['expiry']) ? Ag::jdate($agent['expiry']) : '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">موبایل</label>
                <input name="phone" class="form-control ag-num" value="<?= $isNew ? '' : $e($agent['phone']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">آیدی تلگرام عددی (اعلان‌ها)</label>
                <input name="tg_chat" class="form-control ag-num" value="<?= $isNew ? '' : $e($agent['tg_chat']) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">IPهای مجاز کلید API (جدا با فاصله؛ خالی = همه)</label>
                <input name="api_allow" class="form-control ag-num" dir="ltr" value="<?= $isNew ? '' : $e($agent['api_allow']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">سقف درخواست API در دقیقه (۰ = بی‌نهایت)</label>
                <input name="api_rate" type="number" min="0" class="form-control ag-num" value="<?= $isNew ? 0 : (int) $agent['api_rate'] ?>">
            </div>
            <div class="col-12">
                <label class="form-label">یادداشت (فقط مدیر می‌بیند)</label>
                <input name="note" class="form-control" value="<?= $isNew ? '' : $e($agent['note']) ?>">
            </div>
        </div>
    </div>

    <div class="ag-form-section">
        <h6>دسترسی‌ها</h6>
        <div class="row">
            <?php foreach (Ag::$PERM_LABELS as $key => $label) { if ($key === 'api') { continue; } ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="perms[<?= $key ?>]" value="1" id="perm-<?= $key ?>" <?= !empty($perms[$key]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="perm-<?= $key ?>"><?= $label ?></label>
                    </div>
                </div>
            <?php } ?>
        </div>
        <div class="ag-hint mt-2">اگر «فروش سفارشی» خاموش باشد، نماینده فقط با پلن‌های تعریف‌شده می‌تواند بفروشد.</div>
    </div>

    <div class="ag-form-section">
        <h6>سقف‌ها <span class="ag-hint">(خالی = بدون سقف)</span></h6>
        <div class="row g-2">
            <?php foreach (Ag::$LIMIT_LABELS as $key => $label) { ?>
                <div class="col-6 col-md-4">
                    <label class="form-label small"><?= $label ?></label>
                    <input name="limits[<?= $key ?>]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $val($limits, $key) ?>">
                </div>
            <?php } ?>
        </div>
        <div class="ag-hint mt-2">«سقف اعتبار» یعنی نماینده تا این مبلغ می‌تواند با موجودی منفی هم بفروشد (فروش نسیه).</div>
    </div>

    <div class="ag-form-section">
        <h6>قیمت‌های این نماینده <span class="ag-hint">(خالی = همان قیمت پایه / قیمت بالادست)</span></h6>
        <div class="row g-2 mb-2">
            <div class="col-6 col-md-3"><label class="form-label small">قیمت هر روز</label><input name="pricing[per_day]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $pval($price, 'per_day') ?>" placeholder="پایه: <?= number_format((float) $settings['per_day']) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small">قیمت هر گیگ</label><input name="pricing[per_gb]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $pval($price, 'per_gb') ?>" placeholder="پایه: <?= number_format((float) $settings['per_gb']) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small">تخفیف کلی (درصد)</label><input name="pricing[discount]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $val($price, 'discount') ?>"></div>
        </div>
        <?php if ($plans) { ?>
            <div class="table-responsive">
                <table class="table ag-table mb-0">
                    <thead><tr><th>پلن</th><th>قیمت پایه</th><th>قیمت فروش به این نماینده</th><th>قیمت تمدید</th><th>مخفی</th></tr></thead>
                    <tbody>
                        <?php foreach ($plans as $plan) { $o = isset($price['plans'][(string) $plan['id']]) ? $price['plans'][(string) $plan['id']] : []; ?>
                            <tr>
                                <td><?= $e($plan['name']) ?> <span class="ag-hint">(<?= (int) $plan['days'] ?> روز، <?= (int) $plan['devices'] ?> کاربر)</span></td>
                                <td class="ag-num"><?= number_format($plan['price']) ?></td>
                                <td><input name="plan_price[<?= $plan['id'] ?>][sell]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $pval($o, 'sell') ?>"></td>
                                <td><input name="plan_price[<?= $plan['id'] ?>][renew]" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $pval($o, 'renew') ?>"></td>
                                <td><input type="checkbox" class="form-check-input" name="plan_price[<?= $plan['id'] ?>][hide]" value="1" <?= !empty($o['hide']) ? 'checked' : '' ?>></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div class="ag-hint">هنوز پلنی تعریف نشده است. از «پلن‌ها و قیمت پایه» پلن بسازید.</div>
        <?php } ?>
    </div>

    <button class="btn btn-primary"><?= $isNew ? 'ساخت نماینده' : 'ذخیره تغییرات' ?></button>
</form>

<?php if (!$isNew) { ?>
    <div class="row g-3 mt-2">
        <div class="col-lg-6">
            <div class="ag-form-section">
                <h6>آخرین گردش حساب</h6>
                <div class="table-responsive"><table class="table ag-table mb-0">
                    <?php foreach ($ledger as $row) { ?>
                        <tr>
                            <td><?= Ag::jdate($row->t, true) ?></td>
                            <td><?= isset(Ag::$KIND_LABELS[$row->kind]) ? Ag::$KIND_LABELS[$row->kind] : $e($row->kind) ?></td>
                            <td class="ag-num"><?= $e($row->acct) ?></td>
                            <td class="ag-num <?= $row->amt < 0 ? 'ag-neg' : 'ag-pos' ?>"><?= number_format($row->amt) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!count($ledger)) { ?><tr><td class="text-center">هنوز ردیفی نیست</td></tr><?php } ?>
                </table></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ag-form-section">
                <h6>آخرین کارها</h6>
                <div class="table-responsive"><table class="table ag-table mb-0">
                    <?php foreach ($activity as $row) { ?>
                        <tr>
                            <td><?= Ag::jdate($row->t, true) ?></td>
                            <td><?= isset(Ag::$ACTION_LABELS[$row->action]) ? Ag::$ACTION_LABELS[$row->action] : $e($row->action) ?></td>
                            <td class="ag-num"><?= $e($row->target) ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!count($activity)) { ?><tr><td class="text-center">هنوز کاری ثبت نشده</td></tr><?php } ?>
                </table></div>
            </div>
        </div>
    </div>

    <div class="ag-form-section mt-3">
        <h6>انتقال و حذف</h6>
        <form class="ag-form row g-2 mb-3" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/transfer') ?>" data-confirm="همه اکانت‌های این نماینده به مالک جدید منتقل می‌شود. مطمئن هستید؟">
            <div class="col-8 col-md-5">
                <select name="to" class="form-select form-select-sm" required>
                    <option value="">انتقال همه اکانت‌ها به…</option>
                    <?php foreach (Ag::all() as $p) { if ($p['id'] == $agent['id']) { continue; } ?>
                        <option value="<?= $p['id'] ?>"><?= $e($p['fullname']) ?> (<?= $e($p['username']) ?>)</option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-4 col-md-2"><button class="btn btn-sm btn-outline-primary w-100">انتقال</button></div>
        </form>
        <form class="ag-form" action="<?= baseUrl('agency/agent/' . $agent['id'] . '/delete') ?>" data-confirm="این نماینده حذف شود؟">
            <button class="btn btn-sm btn-outline-danger">حذف نماینده</button>
            <span class="ag-hint">فقط وقتی ممکن است که اکانت و زیرنماینده نداشته باشد و موجودی‌اش صفر باشد.</span>
        </form>
    </div>
<?php } ?>
<?php include __DIR__ . "/_js.php"; ?>
