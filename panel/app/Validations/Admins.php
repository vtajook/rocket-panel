<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Validations;

class Admins
{

    public static function login($pdata)
    {
        $result = array("status" => "success", "messages" => []);

        $validatorRules = [
            'username'        => ['required'],
            'password'        => ['required'],
        ];

        $validatorMsg = [
            'username.required' => 'نام کاربری را وارد کنید',
            'password.required' => 'رمز عبور را وارد کنید',
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

    public static function save($pdata, $editId = null, $uid = null)
    {
        $result     = array("status" => "success", "messages" => []);
        $aModel     = new \App\Models\Admins();

        $userInfo  = null;
        if ($editId) {
            $userInfo =  $aModel->getInfo($editId);
            if (!$userInfo) {
                $result["status"] = "error";
                $result["messages"][] = "اطلاعات کاربر یافت نشد";
                return $result;
            }
        }

        $validatorRules = [
            'fullname'          => ['required'],
            'is_active'         => ['required', 'in:0,1'],
            'username'          => [
                'required',
                function ($attribute, $value,  $fail) use ($aModel, $editId) {
                    if ($value) {
                        if ($aModel->isExistUsername($value, $editId)) {
                            $fail("نام کاربری از قبل وجود دارد");
                        }
                    }
                }
            ],
            'role'              => [
                'required', 'in:admin,employee',
                function ($attribute, $value,  $fail) use ($aModel,  $editId, $uid) {
                    if ($value) {
                        if ($editId != $uid && $editId== 1) {
                            $fail("امکان تغییر برای مدیر اصلی وجود ندارد");
                        }
                    }
                }
            ],
        ];


        $passValidations =  ['regex:|[0-9]|', 'regex:|[a-zA-Z]|', 'min:8'];

        if (!$editId) {
            $validatorRules['password'] = $passValidations;
        } else {
            if (!empty($pdata["password"])) {
                $validatorRules['password'] = $passValidations;
            }
        }


        $validatorMsg = [
            'username.required'             => 'نام کاربری را وارد کنید',
            'password.required'             => 'رمز عبور را وارد کنید',
            'fullname.required'             => 'نام کامل را وارد کنید',
            'role.required'                 => 'نقش کاربر را وارد کنید',
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

        $aModel = new \App\Models\Admins();
        $hasExist = $aModel->checkExist($userId);

        if (!$hasExist) {
            $result["status"] = "error";
            $result["messages"][] = "اطلاعات کاربر یافت نشد";
        }
        return $result;
    }

    public static function delete($manId, $uid)
    {
        $result = array("status" => "success", "messages" => []);

        $aModel = new \App\Models\Admins();
        $hasExist = $aModel->checkExist($manId);

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
}
