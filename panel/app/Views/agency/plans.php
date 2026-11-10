<?php
use App\Agency\Agency as Ag;
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$n = function ($v) { return ($v === null || $v === '') ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
$refund = $settings['refund'];
?>
<?php include __DIR__ . "/_tabs.php"; ?>
<div class="ag-form-section">
    <h6>پلن‌ها</h6>
    <div class="table-responsive">
        <table class="table ag-table">
            <thead><tr><th>نام</th><th>روز</th><th>کاربر همزمان</th><th>حجم (گیگ)</th><th>قیمت فروش</th><th>قیمت تمدید</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($plans as $plan) { ?>
                    <tr>
                        <td><?= $e($plan['name']) ?></td>
                        <td class="ag-num"><?= (int) $plan['days'] ?></td>
                        <td class="ag-num"><?= (int) $plan['devices'] ?></td>
                        <td class="ag-num"><?= $plan['gb'] > 0 ? $n($plan['gb']) : 'نامحدود' ?></td>
                        <td class="ag-num"><?= number_format($plan['price']) ?></td>
                        <td class="ag-num"><?= $plan['renew'] > 0 ? number_format($plan['renew']) : '—' ?></td>
                        <td><span class="badge bg-<?= $plan['enable'] ? 'success' : 'secondary' ?>"><?= $plan['enable'] ? 'فعال' : 'غیرفعال' ?></span></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='agEditPlan(<?= json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>ویرایش</button>
                            <form class="ag-form d-inline" action="<?= baseUrl('agency/plans/' . $plan['id'] . '/delete') ?>" data-confirm="این پلن حذف شود؟"><button class="btn btn-sm btn-outline-danger">حذف</button></form>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$plans) { ?><tr><td colspan="8" class="text-center">هنوز پلنی ندارید</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
    <form class="ag-form row g-2 align-items-end" id="plan-form" action="<?= baseUrl('agency/plans/save') ?>">
        <input type="hidden" name="id" value="0">
        <div class="col-12 col-md-3"><label class="form-label small">نام پلن</label><input name="name" class="form-control form-control-sm" placeholder="مثلاً یک‌ماهه تک‌کاربره" required></div>
        <div class="col-4 col-md-1"><label class="form-label small">روز</label><input name="days" class="form-control form-control-sm ag-num" inputmode="numeric" value="30" required></div>
        <div class="col-4 col-md-1"><label class="form-label small">کاربر</label><input name="devices" class="form-control form-control-sm ag-num" inputmode="numeric" value="1"></div>
        <div class="col-4 col-md-1"><label class="form-label small">حجم</label><input name="gb" class="form-control form-control-sm ag-num" inputmode="numeric" placeholder="نامحدود"></div>
        <div class="col-6 col-md-2"><label class="form-label small">قیمت فروش</label><input name="price" class="form-control form-control-sm ag-num" inputmode="numeric" required></div>
        <div class="col-6 col-md-2"><label class="form-label small">قیمت تمدید</label><input name="renew" class="form-control form-control-sm ag-num" inputmode="numeric" placeholder="همان فروش"></div>
        <div class="col-6 col-md-1"><label class="form-label small">وضعیت</label><select name="enable" class="form-select form-select-sm"><option value="1">فعال</option><option value="0">غیرفعال</option></select></div>
        <div class="col-6 col-md-1"><button class="btn btn-sm btn-primary w-100" id="plan-submit">افزودن</button></div>
    </form>
    <div class="ag-hint mt-2">حجم را خالی بگذارید تا پلن حجم نامحدود باشد. قیمت‌ها قیمت پایه‌اند؛ برای هر نماینده می‌توانید قیمت جدا بگذارید.</div>
</div>

<form class="ag-form" action="<?= baseUrl('agency/settings') ?>">
    <div class="ag-form-section">
        <h6>قیمت پایه فروش سفارشی (بدون پلن)</h6>
        <div class="row g-2">
            <div class="col-6 col-md-3"><label class="form-label small">قیمت هر روز</label><input name="per_day" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($settings['per_day']) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small">قیمت هر گیگ</label><input name="per_gb" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($settings['per_gb']) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small">هر روز اضافه (ویرایش)</label><input name="add_day" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($settings['add_day']) ?>" placeholder="همان قیمت روز"></div>
            <div class="col-6 col-md-3"><label class="form-label small">واحد پول</label><input name="currency" class="form-control form-control-sm" value="<?= $e($settings['currency']) ?>"></div>
            <div class="col-12 col-md-6">
                <div class="form-check mt-2">
                    <input type="hidden" name="device_multiply" value="0">
                    <input class="form-check-input" type="checkbox" name="device_multiply" value="1" id="device-multiply" <?= !empty($settings['device_multiply']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="device-multiply">قیمت سفارشی در تعداد کاربر همزمان ضرب شود</label>
                </div>
            </div>
            <div class="col-6 col-md-3"><label class="form-label small">حداکثر سطح نمایندگی</label><input name="max_levels" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= (int) $settings['max_levels'] ?>"></div>
        </div>
        <div class="ag-hint mt-2">
            <b>حجم را حساب نکن:</b> «قیمت هر گیگ» را صفر (یا خالی) بگذارید؛ در این حالت فقط تعداد روز و پلن حساب می‌شود.<br>
            اگر «قیمت هر روز» هم صفر باشد و پلنی نباشد، فروش برای نماینده‌ها رایگان است و فقط ثبت می‌شود.
        </div>
    </div>

    <div class="ag-form-section">
        <h6>برگشت پول هنگام حذف اکانت</h6>
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label small">روش</label>
                <select name="refund_mode" class="form-select form-select-sm">
                    <option value="none" <?= $refund['mode'] == 'none' ? 'selected' : '' ?>>برگشت پول ندارد</option>
                    <option value="window" <?= $refund['mode'] == 'window' ? 'selected' : '' ?>>فقط در چند ساعت اول</option>
                    <option value="prorated" <?= $refund['mode'] == 'prorated' ? 'selected' : '' ?>>به نسبت روزهای مانده</option>
                </select>
            </div>
            <div class="col-4 col-md-2"><label class="form-label small">تا چند ساعت</label><input name="refund_hours" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($refund['hours']) ?>"></div>
            <div class="col-4 col-md-3"><label class="form-label small">حداکثر مصرف (درصد)</label><input name="refund_max_used" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($refund['max_used_pct']) ?>"></div>
            <div class="col-4 col-md-3"><label class="form-label small">چند درصد برگردد</label><input name="refund_percent" class="form-control form-control-sm ag-num" inputmode="numeric" value="<?= $n($refund['percent']) ?>"></div>
        </div>
    </div>
    <button class="btn btn-primary">ذخیره تنظیمات</button>
</form>
<script>
    function agEditPlan(plan) {
        var form = document.getElementById('plan-form');
        ['id', 'name', 'days', 'devices', 'price'].forEach(function(key) { form.elements[key].value = plan[key]; });
        form.elements['gb'].value = plan.gb > 0 ? plan.gb : '';
        form.elements['renew'].value = plan.renew > 0 ? plan.renew : '';
        form.elements['enable'].value = plan.enable ? '1' : '0';
        document.getElementById('plan-submit').textContent = 'ذخیره';
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>
<?php include __DIR__ . "/_js.php"; ?>
