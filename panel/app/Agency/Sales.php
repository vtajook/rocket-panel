<?php

/**
 * Agency module – what happens when a reseller creates, renews, edits or
 * deletes an account. The manager (role "admin") is never charged or limited;
 * these methods are only called for resellers, except refundOnDelete.
 *
 * Order of every paid operation: lock → permission → limits → quote → charge →
 * do the account work → on failure write a reversal.
 */

namespace App\Agency;

use Morilog\Jalali\Jalalian;

if (!defined('PATH')) die();

class Sales
{
    /** throw unless the logged-in person may touch this account */
    public static function guardOwner($userRow)
    {
        if (!$userRow) {
            throw new AgencyException('اطلاعات کاربر یافت نشد');
        }
        if (!Agency::canAccessOwner($userRow->admin_uname)) {
            throw new AgencyException('این اکانت متعلق به شما نیست');
        }
    }

    /** When a plan is chosen its values replace whatever the form sent. */
    public static function applyPlan($agent, $pdata)
    {
        if (empty($pdata['plan_id'])) {
            return $pdata;
        }
        $plans  = Agency::plansFor($agent);
        $planId = (int) $pdata['plan_id'];
        if (!isset($plans[$planId])) {
            throw new AgencyException('پلن انتخاب‌شده در دسترس نیست');
        }
        $plan = $plans[$planId];
        $pdata['expiry_type'] = 'days';
        $pdata['exp_days']    = (int) $plan['days'];
        $pdata['traffic']     = (float) $plan['gb'];
        $pdata['limit_users'] = (int) $plan['devices'];
        unset($pdata['exp_date']);
        return $pdata;
    }

    private static function daysFromForm($pdata)
    {
        $type = isset($pdata['expiry_type']) ? $pdata['expiry_type'] : 'days';
        if ($type === 'date' && !empty($pdata['exp_date'])) {
            $end = strtotime("tomorrow", Jalalian::fromFormat('Y/m/d', $pdata['exp_date'])->toCarbon()->getTimestamp()) - 1;
            return max(1, (int) ceil(($end - time()) / 86400));
        }
        return isset($pdata['exp_days']) ? (int) $pdata['exp_days'] : 0;
    }

    public static function opFromForm($pdata, $type = 'create')
    {
        return [
            'type'    => $type,
            'days'    => self::daysFromForm($pdata),
            'gb'      => isset($pdata['traffic']) ? (float) $pdata['traffic'] : 0,
            'devices' => isset($pdata['limit_users']) ? (int) $pdata['limit_users'] : 1,
            'plan_id' => isset($pdata['plan_id']) ? (int) $pdata['plan_id'] : 0,
            'count'   => 1,
        ];
    }

    public static function create($agent, $pdata, $uModel, $uid)
    {
        return Agency::withLock(function () use ($agent, $pdata, $uModel, $uid) {
            Agency::requirePerm($agent, 'create');
            Agency::requireCanSell($agent);

            $op = self::opFromForm($pdata, 'create');
            Agency::checkLimits($agent, $op);
            $quote  = Agency::quote($agent, $op);
            $saleId = Agency::charge($quote, ['kind' => 'sale', 'acct' => $pdata['username']]);

            try {
                $uModel->saveUsers($pdata, $uid);
            } catch (\Exception $err) {
                Agency::reverse($saleId);
                throw $err;
            }

            $row = db('users')->where('username', trim($pdata['username']))->first();
            if ($row) {
                Agency::tagSale($saleId, $row->id, $row->username);
            }
            Agency::log('user.create', $pdata['username'], [
                'days' => $quote['days'], 'devices' => $quote['devices'], 'cost' => $quote['cost'],
                'plan' => $quote['plan'] ? $quote['plan']['name'] : null,
            ]);

            return $quote;
        });
    }

    public static function bulk($agent, $pdata, $uModel)
    {
        return Agency::withLock(function () use ($agent, $pdata, $uModel) {
            Agency::requirePerm($agent, 'create');
            Agency::requirePerm($agent, 'bulk');
            Agency::requireCanSell($agent);

            $count = max(1, (int) $pdata['users_count']);
            if ($count > 200) {
                throw new AgencyException('در هر بار حداکثر ۲۰۰ اکانت می‌توانید بسازید');
            }

            $op = self::opFromForm($pdata, 'create');
            $op['count'] = $count;
            Agency::checkLimits($agent, $op);

            // make sure the whole batch is affordable before taking anything
            $total = Agency::quote($agent, $op);
            foreach ($total['rows'] as $index => $row) {
                if ($row['cost'] > 0 && Agency::spendable($row['agent']) < $row['cost']) {
                    throw new AgencyException($index === 0
                        ? 'موجودی کیف پول برای این تعداد کافی نیست. هزینه: ' . Agency::money($row['cost'])
                        : 'موجودی کیف پول نماینده بالادست شما کافی نیست');
                }
            }

            // one sale per account keeps daily limits and reports exact
            $op['count'] = 1;
            $single  = Agency::quote($agent, $op);
            $saleIds = [];
            for ($i = 0; $i < $count; $i++) {
                $saleIds[] = Agency::charge($single, ['kind' => 'sale', 'acct' => $pdata['prefix_username'] . '*']);
            }

            $added = 0;
            try {
                $added = (int) $uModel->createBulkUsers($pdata);
            } catch (\Exception $err) {
                $added = 0;
            }
            // give back what was charged for accounts that were not created
            for ($i = $added; $i < $count; $i++) {
                Agency::reverse($saleIds[$i], 'اکانت ساخته نشد (نام تکراری یا خطا)');
            }

            Agency::log('user.bulk', $pdata['prefix_username'], [
                'requested' => $count, 'created' => $added, 'cost' => $single['cost'] * $added,
            ]);

            return $added;
        });
    }

    public static function renew($agent, $userInfo, $pdata, $uModel)
    {
        return Agency::withLock(function () use ($agent, $userInfo, $pdata, $uModel) {
            self::guardOwner($userInfo);
            Agency::requirePerm($agent, 'renew');
            Agency::requireCanSell($agent);

            $op = [
                'type'       => 'renew',
                'days'       => (int) $pdata['renewal_days'],
                'gb'         => (float) $userInfo->traffic / 1024,
                'devices'    => (int) $userInfo->limit_users,
                'plan_id'    => isset($pdata['plan_id']) ? (int) $pdata['plan_id'] : 0,
                'was_active' => $userInfo->status === 'active',
            ];
            $quote = Agency::quote($agent, $op);
            $op['days'] = $quote['days'];
            Agency::checkLimits($agent, $op);

            $saleId = Agency::charge($quote, [
                'kind' => 'renew', 'acct' => $userInfo->username, 'user_id' => $userInfo->id,
            ]);

            $pdata['renewal_days'] = $quote['days'];
            try {
                $uModel->renewalUsers($pdata, $userInfo->id);
            } catch (\Exception $err) {
                Agency::reverse($saleId);
                throw $err;
            }

            Agency::log('user.renew', $userInfo->username, ['days' => $quote['days'], 'cost' => $quote['cost']]);

            return $quote;
        });
    }

    public static function edit($agent, $userInfo, $pdata, $uModel, $uid)
    {
        return Agency::withLock(function () use ($agent, $userInfo, $pdata, $uModel, $uid) {
            self::guardOwner($userInfo);
            Agency::requirePerm($agent, 'edit');

            $now        = time();
            $newDevices = (int) $pdata['limit_users'];
            $newGb      = (float) $pdata['traffic'];
            $oldGb      = (float) $userInfo->traffic / 1024;

            if ($userInfo->start_date) {
                $remaining = max(0, (int) ceil(($userInfo->end_date - $now) / 86400));
                $extraDays = 0;
                if (!empty($pdata['exp_date'])) {
                    $newEnd    = Jalalian::fromFormat('Y/m/d', $pdata['exp_date'])->toCarbon()->getTimestamp();
                    $extraDays = max(0, (int) ceil(($newEnd - $userInfo->end_date) / 86400));
                }
            } else {
                $remaining = (int) $userInfo->expiry_days;
                $extraDays = max(0, self::daysFromForm($pdata) - $remaining);
            }

            $op = [
                'type'           => 'edit',
                'days'           => $remaining + $extraDays,
                'gb'             => $newGb,
                'devices'        => $newDevices,
                'extra_days'     => $extraDays,
                'extra_gb'       => ($newGb > 0 && $oldGb > 0) ? max(0, $newGb - $oldGb) : 0,
                'extra_devices'  => max(0, $newDevices - (int) $userInfo->limit_users),
                'remaining_days' => $remaining,
            ];
            if ($oldGb > 0 && $newGb <= 0 && !Agency::can($agent, 'unlimited')) {
                throw new AgencyException('تبدیل اکانت به حجم نامحدود برای شما مجاز نیست');
            }

            $paid   = $op['extra_days'] > 0 || $op['extra_gb'] > 0 || $op['extra_devices'] > 0;
            $saleId = null;
            $quote  = null;
            if ($paid) {
                Agency::requireCanSell($agent);
                Agency::checkLimits($agent, $op);
                $quote  = Agency::quote($agent, $op);
                $kind   = $op['extra_days'] > 0 ? 'adddays' : ($op['extra_gb'] > 0 ? 'addgb' : 'adddev');
                $quote['days'] = $op['extra_days'];
                $quote['gb']   = $op['extra_gb'];
                $saleId = Agency::charge($quote, [
                    'kind' => $kind, 'acct' => $userInfo->username, 'user_id' => $userInfo->id,
                ]);
            }

            try {
                $uModel->saveUsers($pdata, $uid, $userInfo->id);
            } catch (\Exception $err) {
                if ($saleId) {
                    Agency::reverse($saleId);
                }
                throw $err;
            }

            Agency::log('user.edit', $userInfo->username, $paid ? [
                'extra_days' => $op['extra_days'], 'extra_devices' => $op['extra_devices'], 'cost' => $quote['cost'],
            ] : null);

            return $quote;
        });
    }

    public static function toggle($agent, $userInfo, $uModel, $uid)
    {
        return Agency::withLock(function () use ($agent, $userInfo, $uModel, $uid) {
            self::guardOwner($userInfo);
            Agency::requirePerm($agent, 'suspend');
            if ($userInfo->status !== 'active') {
                Agency::requireCanSell($agent);
                Agency::checkLimits($agent, ['type' => 'activate', 'was_active' => false, 'devices' => 1]);
            }
            $uModel->toggleActive($userInfo->id, $uid);
            Agency::log('user.toggle', $userInfo->username, $userInfo->status === 'active' ? 'غیرفعال شد' : 'فعال شد');

        });
    }

    public static function delete($agent, $userInfo, $uModel, $uid)
    {
        self::guardOwner($userInfo);
        Agency::requirePerm($agent, 'delete');
        $refund = Agency::refundOnDelete($userInfo);
        $uModel->deleteUser($userInfo->id, $uid);
        Agency::log('user.delete', $userInfo->username, $refund ? ['refund' => $refund] : null);
        return $refund;
    }
}
