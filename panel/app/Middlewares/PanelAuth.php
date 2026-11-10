<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Middlewares;

class PanelAuth
{

  private $cont;

  function __construct($cont)
  {
    $this->cont = $cont;
  }

  private $excludeListStatic = array(
    '/login',
    '/ajax/login',
  );

  private $excludeListPattern = array();

  private function excludeCheck($uri)
  {

    if (in_array($uri, $this->excludeListStatic)) {
      return true;
    }

    foreach ($this->excludeListPattern as $pattern) {
      if (preg_match("|^" . $pattern . "|is", $uri)) {
        return true;
      }
    }
    return false;
  }

  public function __invoke($request, $response, $next)
  {
    $uri = $request->getUri()->getPath();
    $uri = ltrim($uri, '/');

    $pass = false;
    if ($this->excludeCheck($uri)) {
      $pass = true;
    } else {
      $userId = session()->get("admin_login");
      if (!$userId && strpos($uri, 'ajax/') === 0) {
        // API key (X-API-Key or Bearer) – only for the ajax endpoints, never creates a session
        $key = $request->getHeaderLine('X-API-Key');
        if ($key === '' && preg_match('/^Bearer\s+(vl_[0-9a-f]{48})$/', $request->getHeaderLine('Authorization'), $m)) {
          $key = $m[1];
        }
        if ($key !== '') {
          $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
          $userId = \App\Agency\Extras::authenticate($key, $ip, $request->getMethod(), $uri);
          if (!$userId) {
            return $response->withStatus(401)->withJson(["status" => "error", "messages" => ["کلید API نامعتبر است"]]);
          }
        }
      }
      if ($userId) {
        $aModal = new \App\Models\Admins();
        $userInfo = $aModal->getInfo($userId);
        $blocked = true;
        if ($userInfo) {
          $agent   = \App\Agency\Agency::find($userInfo->id);
          $blocked = !$agent || \App\Agency\Agency::blockReason($agent) !== '';
          // a password change / ban bumps token_ver and ends older sessions
          $sessVer = session()->get("token_ver");
          if (!$blocked && $sessVer !== null && $agent && (int) $sessVer !== (int) $agent['token_ver']) {
            $blocked = true;
          }
        }
        if ($userInfo && !$blocked) {
          $request = $request->withAttribute('uid', $userId);
          $request = $request->withAttribute('userInfo', $userInfo);
          $this->cont["userInfo"] = $userInfo;
          session()->set("userInfo", $userInfo);
          $pass = true;
        } else {
          session()->delete('admin_login');
          session()->delete('imp_admin');
          session()->delete('token_ver');
        }
      }
    }
    if ($pass) {
      $response = $next($request, $response);
      return $response;
    }
    return $response->withStatus(302)->withHeader("location", baseUrl("/login"));
  }
}
