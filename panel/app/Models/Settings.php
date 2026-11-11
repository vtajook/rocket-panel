<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Models;

use \App\Libraries\UserShell;

class Settings extends \App\Models\BaseModel
{

    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'value'];


    public function saveMainSettings($pdata, $uid)
    {
        $validKeys = [
            "ssh_port",
            "udp_port",
            "multiuser",
            "fake_url",
            "connected_text",
            "domain_url",
            "calc_traffic"
        ];

        if (is_array($pdata)) {

            $serverIp = getServerIp();
            
            foreach ($pdata as $key => $value) {
                if (in_array($key, $validKeys)) {

                    $this->updateOrCreate(['name' => $key], ["name" => $key, "value" => $value]);

                    if ($key == "ssh_port") {
                        UserShell::updateSshPort($value);
                    }
                    if ($key == "udp_port") {
                        UserShell::updateUdpPort($value);
                    }

                    if ($key == "connected_text") {
                        UserShell::updateConnectedText($value);
                    }

                    if ($key == "domain_url" && !empty($value)) {
                        UserShell::updateDomainUrl($value);
                    }
                    if ($key == "fake_url") {
                        $url = !empty($value) ? $value : "local";
                        UserShell::updateFakeUrl($url);
                    }
                }
            }
        }
    }

    public function saveUsersPanel($pdata, $uid)
    {
        $validKeys = [
            "support_url",
            "theme",
            "welecom_text",
            "logo_url",
        ];

        if (is_array($pdata)) {

            $saveData = [];
            foreach ($pdata as $key => $value) {
                if (in_array($key, $validKeys)) {
                    $saveData[$key] = $value;
                }
            }
            $this->updateOrCreate(
                ['name' => "users_panel"],
                [
                    'name' => "users_panel", "value" => json_encode($saveData)
                ]
            );
        }
    }

    public function getSettings()
    {
        $result = [];

        $query = db($this->table)->get();
        if ($query->count()) {
            $rows   = $query->toArray();

            foreach ($rows as $row) {
                $result[$row->name] = $row->value;
            }
        }

        return $result;
    }

    public static function getSetting($name)
    {
        $query = db("settings")->where("name", $name)->get();

        if ($query->count()) {
            $row = $query->first();
            return $row->value;
        }

        return false;
    }
}
