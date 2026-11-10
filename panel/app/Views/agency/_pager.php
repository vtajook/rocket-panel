<?php
// expects $list (rows/page/pages/total), $baseLink and $filters
$agQuery = $filters; unset($agQuery['page'], $agQuery['csv']);
$agLink  = function ($page) use ($baseLink, $agQuery) { return baseUrl($baseLink . '?' . http_build_query(array_merge($agQuery, ['page' => $page]))); };
?>
<div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
    <span class="ag-hint"><?= number_format($list['total']) ?> ردیف — صفحه <?= $list['page'] ?> از <?= $list['pages'] ?></span>
    <div class="btn-group btn-group-sm">
        <a class="btn btn-outline-secondary <?= $list['page'] <= 1 ? 'disabled' : '' ?>" href="<?= $agLink($list['page'] - 1) ?>">قبلی</a>
        <a class="btn btn-outline-secondary <?= $list['page'] >= $list['pages'] ? 'disabled' : '' ?>" href="<?= $agLink($list['page'] + 1) ?>">بعدی</a>
    </div>
</div>
