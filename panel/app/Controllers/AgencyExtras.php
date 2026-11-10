<?php

namespace App\Controllers;

use \App\Agency\Agency as Core;
use \App\Agency\Extras;
use \App\Agency\AgencyException;

/** customers, transfer requests and API keys */
class AgencyExtras extends BaseController
{
    private function ok($response, $data = [])
    {
        return $response->withStatus(200)->withJson(array_merge(["status" => "success"], $data));
    }

    private function fail($response, $message, $status = 400)
    {
        return $response->withStatus($status)->withJson(["status" => "error", "messages" => [$message]]);
    }

    private function page($view, $title, $menu, $data = [])
    {
        enqueueStyleHeader(assets("css/agency.css"));
        $data["pageTitle"]   = $title;
        $data["viewContent"] = "agency/" . $view . ".php";
        $data["activeMenu"]  = $menu;
        $data["activePage"]  = "agency";
        $data["settings"]    = Core::settings();
        return $this->render($data);
    }

    private function me()
    {
        $me = Core::me();
        return ($me && !Core::isAdmin($me)) ? $me : null;
    }

    /* ---- customers (reseller) ---- */

    public function customersPage($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
        }
        $rows = Extras::customers($me['id']);
        $counts = [];
        foreach (db('users')->whereIn('customer_id', array_map(function ($r) { return $r->id; }, $rows) ?: [0])
            ->selectRaw('customer_id, count(*) c')->groupBy('customer_id')->get() as $r) {
            $counts[$r->customer_id] = $r->c;
        }
        return $this->page("customers", "مشتریان من", "agency-my-customers", ["rows" => $rows, "counts" => $counts]);
    }

    public function saveCustomer($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) { return $this->fail($response, "دسترسی ندارید", 403); }
        $p = (array) $request->getParsedBody();
        try {
            Extras::saveCustomer($me['id'], (int) getArrayValue($p, "id", 0), [
                'name' => getArrayValue($p, "name"), 'phone' => getArrayValue($p, "phone"),
                'tg_id' => getArrayValue($p, "tg_id"), 'note' => getArrayValue($p, "note"),
            ]);
        } catch (AgencyException $e) {
            return $this->fail($response, $e->getMessage());
        }
        return $this->ok($response);
    }

    public function deleteCustomer($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) { return $this->fail($response, "دسترسی ندارید", 403); }
        Extras::deleteCustomer($me['id'], (int) $args['id']);
        return $this->ok($response);
    }

    public function assignCustomer($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) { return $this->fail($response, "دسترسی ندارید", 403); }
        $p = (array) $request->getParsedBody();
        try {
            Extras::assignCustomer($me['id'], trim((string) getArrayValue($p, "username")), (int) getArrayValue($p, "customer_id", 0));
        } catch (AgencyException $e) {
            return $this->fail($response, $e->getMessage());
        }
        return $this->ok($response);
    }

    /* ---- transfer requests ---- */

    public function myRequests($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
        }
        $rows = db('agency_requests')->where('agent_id', $me['id'])->orderBy('id', 'desc')->limit(100)->get()->toArray();
        return $this->page("requests", "درخواست‌های انتقال", "agency-my-requests", ["rows" => $rows, "manager" => false, "names" => []]);
    }

    public function createRequest($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) { return $this->fail($response, "دسترسی ندارید", 403); }
        $p = (array) $request->getParsedBody();
        try {
            Extras::createRequest($me, trim((string) getArrayValue($p, "acct")), trim((string) getArrayValue($p, "target")), getArrayValue($p, "text"));
        } catch (AgencyException $e) {
            return $this->fail($response, $e->getMessage());
        }
        return $this->ok($response);
    }

    public function requestsPage($request, $response, $args)
    {
        $rows = db('agency_requests')->orderByRaw("status = 'pending' desc")->orderBy('id', 'desc')->limit(200)->get()->toArray();
        $names = [];
        foreach (Core::agents() as $a) { $names[$a['id']] = $a['username']; }
        return $this->page("requests", "درخواست‌های انتقال", "agency-requests", ["rows" => $rows, "manager" => true, "names" => $names]);
    }

    public function decideRequest($request, $response, $args)
    {
        $p = (array) $request->getParsedBody();
        try {
            Extras::decideRequest((int) $args['id'], getArrayValue($p, "approve", "0") == "1", getArrayValue($p, "answer"));
        } catch (AgencyException $e) {
            return $this->fail($response, $e->getMessage());
        }
        return $this->ok($response);
    }

    /* ---- API keys ---- */

    public function keysPage($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
        }
        $new = session()->get("new_api_key");
        session()->delete("new_api_key");
        return $this->page("keys", "کلید API", "agency-my-keys", [
            "rows" => Extras::keys($me['id']), "me" => $me, "newKey" => $new,
            "canApi" => Core::can($me, 'api'),
        ]);
    }

    public function newKey($request, $response, $args)
    {
        $me = $this->me();
        if (!$me || !Core::can($me, 'api')) { return $this->fail($response, "دسترسی ندارید", 403); }
        $p = (array) $request->getParsedBody();
        try {
            $plain = Extras::createKey($me['id'], getArrayValue($p, "name"));
        } catch (AgencyException $e) {
            return $this->fail($response, $e->getMessage());
        }
        session()->set("new_api_key", $plain);
        Core::log('key.create');
        return $this->ok($response);
    }

    public function deleteKey($request, $response, $args)
    {
        $me = $this->me();
        if (!$me) { return $this->fail($response, "دسترسی ندارید", 403); }
        Extras::deleteKey($me['id'], (int) $args['id']);
        Core::log('key.delete');
        return $this->ok($response);
    }

    public function apiLogPage($request, $response, $args)
    {
        $rows = db('agency_api_log')->orderBy('id', 'desc')->limit(300)->get()->toArray();
        $names = [];
        foreach (Core::agents() as $a) { $names[$a['id']] = $a['username']; }
        return $this->page("apilog", "لاگ API", "agency-apilog", ["rows" => $rows, "names" => $names]);
    }
}
