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
            "sshd_port",
            "udp_port",
            "multiuser",
            "fake_url",
            "connected_text",
            "domain_url",
            "calc_traffic"
        ];

        if (is_array($pdata)) {

            $serverIp = getServerIp();
            $error    = null;

            // real sshd port: always applied as typed (not stored, the live value is read from the sockets).
            // The "SSH port in the config" field below is independent: it is only what customers' links show.
            $newReal = isset($pdata["sshd_port"]) ? (int) $pdata["sshd_port"] : 0;
            if ($newReal >= 1 && $newReal !== UserShell::sshdPort()) {
                $error = UserShell::updateSshdPort($newReal);
            }

            foreach ($pdata as $key => $value) {
                if ($key === "sshd_port") {
                    continue;
                }
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
                        $url = !empty($value) ? $value : "https://google.com";
                        UserShell::updateFakeUrl($url);
                    }
                }
            }
            return $error;
        }
        return null;
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
