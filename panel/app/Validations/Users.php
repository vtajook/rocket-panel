<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Validations;

use Morilog\Jalali\Jalalian;

class Users
{

    public static function save($pdata, $editId = null)
    {
        $result     = array("status" => "success", "messages" => []);
        $uModel     = new \App\Models\Users();

        $userInfo  = null;
        if ($editId) {
            $userInfo =  $uModel->getInfo($editId);

            if (!$userInfo) {
                $result["status"] = "error";
                $result["messages"][] = "اطلاعات کاربر یافت نشد";
                return $result;
            } else {
                if (!\App\Agency\Agency::canAccessOwner($userInfo->admin_uname)) {
                    $result["status"] = "error";
                    $result["messages"][] = "شما مجوز ویرایش این کاربر را ندارید";
                    return $result;
                }
            }
        }

        $validatorRules = [
            'password'          => ['required'],
            'email'             => ['nullable', 'email'],
            'mobile'            => ['nullable'],
            'desc'              => ['nullable'],
            'limit_users'       => ['required', 'numeric', 'min:1'],
            'expiry_type'       => ['required', 'in:days,date'],
            'exp_days'          => ['required_if:expiry_type,days', 'numeric', 'min:1'],
            'traffic'           => ['required', 'numeric', 'min:0'],
            'exp_date'          => [
                'required_if:expiry_type,date',
                'string',
                'regex:/^\d{4}\/\d{2}\/\d{2}$/',
                function ($attribute, $value,  $fail) use ($uModel, $editId) {
                    if ($value) {
                        $value  = strtotime(Jalalian::fromFormat('Y/m/d', $value)->toCarbon());
                        if ($value < time()) {
                            $fail("تاریخ پایان نمی تواند کوچکتر از تاریخ فعلی باشد");
                        }
                    }
                },
            ],
        ];

        if (!$editId) {
            $validatorRules['username'] = [
                'required',
                function ($attribute, $value,  $fail) use ($uModel, $editId) {
                    if ($value) {
                        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{1,31}$/', $value) || preg_match('/^[0-9]+$/', $value)) {
                            $fail("نام کاربری فقط می‌تواند حروف انگلیسی، عدد و _ . - باشد (۲ تا ۳۲ حرف، نه فقط عدد)");
                            return;
                        }
                        // a name that already belongs to the server itself must never become an account
                        if (function_exists('posix_getpwnam') && posix_getpwnam($value) && !$uModel->isExistUsername($value)) {
                            $fail("این نام کاربری قابل استفاده نیست");
                            return;
                        }
                        if ($uModel->isExistUsername($value, $editId)) {
                            $fail("لطفا از نام کاربری دیگری استفاده کنید");
                        }
                    }
                },
            ];
        } else {

            $startDate = $userInfo->start_date;
            if ($startDate) {
                unset($validatorRules["expiry_type"]);
                unset($validatorRules["exp_date"]);
                unset($validatorRules["exp_days"]);
                $validatorRules["exp_date"] = [
                    'required', 'regex:/^\d{4}\/\d{2}\/\d{2}$/',
                    function ($attribute, $value,  $fail) use ($startDate) {
                        if ($value) {
                            $value  = strtotime(Jalalian::fromFormat('Y/m/d', $value)->toCarbon());
                            if ($value < $startDate) {
                                $fail("تاریخ پایان نمی تواند کوچکتر از تاریخ شروع باشد");
                            }
                        }
                    }
                ];
            }
        }

        $validatorMsg = [
            'username.required'             => 'نام کاربری را وارد کنید',
            'password.required'             => 'رمز عبور را وارد کنید',
            'password.email'                => 'ایمیل را به صورت صحیح وارد کنید',

            'limit_users.required'          => 'وارد کردن تعداد کاربران همزمان الزامی است',
            'limit_users.numeric'           => 'تعداد کاربران همزمان به صورت عدد وارد کنید',
            'limit_users.min'               => 'حداقل تعداد کاربران همزمان 1 است',

            'expiration_time.required'      => 'زمان انقضاء را وارد کنید',
            'expiration_time.in'            => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'exp_date.required_if'          => 'زمان انقضاء را وارد کنید',
            'exp_date.numeric'              => 'زمان انقضاء را به صورت صحیح وارد کنید',
            'exp_date.min'                  => 'زمان انقضاء را به صورت صحیح وارد کنید',
            'exp_date.regex'                => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'exp_days.required_if'          => 'زمان انقضاء را وارد کنید',
            'exp_days.numeric'              => 'زمان انقضاء را به صورت صحیح وارد کنید',
            'exp_days.min'                  => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'traffic.required'              => 'وارد کردن ترافیک الزامی است',
            'traffic.numeric'               => 'مقدار ترافیک را به صورت عددی وارد کنید',
            'traffic.min'                   => 'حداقل مقدار ترافیک 1 گیگابایت است',
        ];

        $validation = validator()->make(
            $pdata,
            $validatorRules,
            $validatorMsg
        );

        if ($validation->fails()) {
            $messages = $validation->errors()->getMessages();
            $messages[]                 = "لطفا ورودی های خود را کنترل کنید";
            $result["messages"]         = array_reverse($messages);
            $result["error_fields"]     = array_keys($messages);
            $result["status"]           = "error";
        }

        return $result;
    }

    public static function renewal($pdata, $editId)
    {
        $result     = array("status" => "success", "messages" => []);
        $uModel     = new \App\Models\Users();

        $userInfo =  $uModel->getInfo($editId);

        if (!$userInfo) {
            $result["status"] = "error";
            $result["messages"][] = "اطلاعات کاربر یافت نشد";
            return $result;
        } else {
            if (!\App\Agency\Agency::canAccessOwner($userInfo->admin_uname)) {
                $result["status"] = "error";
                $result["messages"][] = "شما مجوز ویرایش این کاربر را ندارید";
                return $result;
            }
        }

        $validatorRules = [
            'renewal_days'      => ['required', 'numeric', 'min:1'],
            'renewal_date'      => ['required', 'in:yes,no'],
            'renewal_traffic'   => ['required', 'in:yes,no'],
        ];



        $validatorMsg = [
            'renewal_days.required'         => 'روزهای تمدید را وارد کنید',
            'renewal_days.required'         => 'روزهای تمدید را به صورت صحیح وارد کنید',
            'renewal_days.email'            => 'روزهای تمدید را به صورت صحیح وارد کنید',
        ];

        $validation = validator()->make(
            $pdata,
            $validatorRules,
            $validatorMsg
        );

        if ($validation->fails()) {
            $messages = $validation->errors()->getMessages();
            $messages[]                 = "لطفا ورودی های خود را کنترل کنید";
            $result["messages"]         = array_reverse($messages);
            $result["error_fields"]     = array_keys($messages);
            $result["status"]           = "error";
        }

        return $result;
    }

    public static function hasExist($userId)
    {
        $result = array("status" => "success", "messages" => []);

        $uModel = new \App\Models\Users();
        $hasExist = $uModel->checkExist($userId);

        if (!$hasExist) {
            $result["status"] = "error";
            $result["messages"][] = "اطلاعات کاربر یافت نشد";
        }
        return $result;
    }

    public static function delete($manId, $uid)
    {
        $result = array("status" => "success", "messages" => []);

        $uModel = new \App\Models\Users();
        $hasExist = $uModel->checkExist($manId);

        if (!$hasExist) {
            $result["status"] = "error";
            $result["messages"][] = "اطلاعات کاربر یافت نشد";
        } else {
            if ($manId  == $uid) {
                $result["status"] = "error";
                $result["messages"][] = "کاربر انتخابی قابل حذف نیست";
            }
        }

        return $result;
    }

    public static function deleteBulk($pdata)
    {
        $result     = array("status" => "success", "messages" => []);
        $uModel     = new \App\Models\Users();

        $validatorRules = [
            'users'          => ['required', 'array'],
            'users.*'        => [function ($attribute, $value,  $fail) use ($uModel) {
                if ($value) {
                    if (!$uModel->checkExist($value)) {
                        $fail("کاربر با شناسه $value یافت نشد");
                    }
                }
            }]
        ];

        $validatorMsg = [
            'users.required'    => 'شناسه کاربران را ارسال کنید',
            'users.array'       => 'شناسه کاربران را به صورت آرایه ارسال کنید',
        ];

        $validation = validator()->make(
            $pdata,
            $validatorRules,
            $validatorMsg
        );

        if ($validation->fails()) {
            $messages = $validation->errors()->getMessages();
            $result["messages"]    = array_reverse($messages);
            $result["status"]      = "error";
        }

        return $result;
    }

    public static function bulkUsers($pdata)
    {
        $result     = array("status" => "success", "messages" => []);

        $validatorRules = [
            'users_count'           => ['required', 'numeric', 'min:1'],
            'username_start_num'    => ['required', 'numeric'],
            'password_type'         => ['required', 'in:number,letter,number_letter'],
            'password_len'          => ['required', 'numeric', 'min:4'],
            'limit_users'           => ['required', 'numeric', 'min:1'],
            'expiry_type'           => ['required', 'in:days,date'],
            'exp_date'              => ['required_if:expiry_type,date', 'string'],
            'exp_days'              => ['required_if:expiry_type,days', 'numeric', 'min:1'],
            'traffic'               => ['required', 'numeric', 'min:0'],
            'prefix_username'       => ['required',  function ($attribute, $value,  $fail) {
                if ($value) {
                    if (!preg_match('/^[A-Za-z][A-Za-z0-9-_]*$/', $value)) {
                        $fail("پیشوند نام کاربری را به صورت صحیح وارد کنید");
                    }
                }
            }]
        ];


        $validatorMsg = [
            'username.required'             => 'نام کاربری را وارد کنید',
            'password.required'             => 'رمز عبور را وارد کنید',
            'password.email'                => 'ایمیل را به صورت صحیح وارد کنید',

            'limit_users.required'          => 'وارد کردن تعداد کاربران همزمان الزامی است',
            'limit_users.numeric'           => 'تعداد کاربران همزمان به صورت عدد وارد کنید',
            'limit_users.min'               => 'حداقل تعداد کاربران همزمان 1 است',

            'expiration_time.required'      => 'زمان انقضاء را وارد کنید',
            'expiration_time.in'            => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'exp_date.required_if'          => 'زمان انقضاء را وارد کنید',
            'exp_date.numeric'              => 'زمان انقضاء را به صورت صحیح وارد کنید',
            'exp_date.min'                  => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'exp_days.required_if'          => 'زمان انقضاء را وارد کنید',
            'exp_days.numeric'              => 'زمان انقضاء را به صورت صحیح وارد کنید',
            'exp_days.min'                  => 'زمان انقضاء را به صورت صحیح وارد کنید',

            'traffic.required'              => 'وارد کردن ترافیک الزامی است',
            'traffic.numeric'               => 'مقدار ترافیک را به صورت عددی وارد کنید',
            'traffic.min'                   => 'حداقل مقدار ترافیک 1 گیگابایت است',
        ];

        $validation = validator()->make(
            $pdata,
            $validatorRules,
            $validatorMsg
        );

        if ($validation->fails()) {
            $messages = $validation->errors()->getMessages();
            $messages[]                 = "لطفا ورودی های خود را کنترل کنید";
            $result["messages"]         = array_reverse($messages);
            $result["error_fields"]     = array_keys($messages);
            $result["status"]           = "error";
        }

        return $result;
    }
}
