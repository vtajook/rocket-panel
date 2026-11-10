<div class="row justify-content-center">
    <div class="col-lg-4">
        <div class="card shadow-lg border-0">
            <div class="card-body">
                <form method="post">
                    <div class="text-center">
                        <img class="mb-4" src="<?= baseUrl("assets/images/logo.png") ?>" width="72">
                        <h1 class="h6 mb-3 fw-normal">لطفا با اطلاعات کاربری خود وارد شوید</h1>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" name="username" value="<?= $username ?>" class="form-control" id="floatingInput" placeholder="نام کاربری را وارد کنید">
                        <label for="floatingInput">نام کاربری</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" name="password" value="<?= $password ?>" class="form-control" id="floatingPassword" placeholder="رمز عبور را وارد کنید">
                        <label for="floatingPassword">رمز عبور</label>
                    </div>
                    <button class="btn btn-primary w-100 py-2" type="submit">وارد شوید</button>

                    <?php if (!empty($error)) { ?>
                        <div class="text-danger mt-2 small text-center"><?= $error ?></div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>