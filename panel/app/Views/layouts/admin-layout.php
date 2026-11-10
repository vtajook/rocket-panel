<?php include "sections/head.php"; ?>

<?php include "sections/sidemenu.php"; ?>
<div class="main-content-wrap d-flex flex-column h-100">
    <div class="flex-shrink-0 mb-2">
        <div class="container-fluid mb-1">
            <?php include "sections/navbar.php"; ?>
            <div class="main-content pt-4">
                <?php include viewContentPath($viewContent); ?>
            </div>
        </div>
    </div>

</div>

<?php include "sections/footer.php"; ?>