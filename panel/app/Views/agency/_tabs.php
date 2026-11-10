<?php $agTab = isset($activeMenu) ? $activeMenu : ''; ?>
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency' ? 'active' : '' ?>" href="<?= baseUrl('agency') ?>">نمایندگان</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-reports' ? 'active' : '' ?>" href="<?= baseUrl('agency/reports') ?>">گزارش فروش</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-ledger' ? 'active' : '' ?>" href="<?= baseUrl('agency/ledger') ?>">دفتر مالی</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-activity' ? 'active' : '' ?>" href="<?= baseUrl('agency/activity') ?>">لاگ فعالیت</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-requests' ? 'active' : '' ?>" href="<?= baseUrl('agency/requests') ?>">درخواست‌ها</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-apilog' ? 'active' : '' ?>" href="<?= baseUrl('agency/apilog') ?>">لاگ API</a></li>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $agTab == 'agency-settings' ? 'active' : '' ?>" href="<?= baseUrl('agency/plans') ?>">پلن‌ها و قیمت پایه</a></li>
</ul>
