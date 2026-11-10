<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Middlewares;

class PanelPerms
{

    private $cont;

    function __construct($container)
    {
        $this->cont = $container;
    }

    private $excludeListStatic = array(
        '/',
    );

    private $excludeListPattern = array();

    private function excludeCheck($uri)
    {
        if (in_array($uri, $this->excludeListStatic)) {
            return true;
        }
        // dynamic rule check
        foreach ($this->excludeListPattern as $pattern) {
            if (preg_match("|^" . $pattern . "|is", $uri)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Everything is manager-only unless it is listed here. A reseller gets the
     * account pages (which enforce ownership themselves) and its own agency pages.
     */
    private $agentAllowed = array(
        '',
        'dashboard',
        'logout',
        'users',
        'users/online',
        'ajax/users',
        'ajax/users/list',
        'ajax/users/online-list',
        'ajax/users/kill-pid',
        'ajax/users/purge-expired',
        'ajax/users/bulk/create',
        'ajax/users/bulk/delete',
        'ajax/users/\\d+',
        'ajax/users/\\d+/toggle-active',
        'ajax/users/\\d+/renewal',
        'ajax-views/users/add',
        'ajax-views/users/bulk-add',
        'ajax-views/users/\\d+/edit',
        'ajax-views/users/\\d+/info',
        'agency/my(/.*)?',
        'agency/back',
    );

    public function __invoke($request, $response, $next)
    {
        $uri = $request->getUri()->getPath();
        $uri = trim($uri, '/');

        $userInfo = $request->getAttribute('userInfo');
        $role     = $userInfo ? $userInfo->role : '';

        if ($role != "admin") {
            $allowed = false;
            foreach ($this->agentAllowed as $pattern) {
                if (preg_match("#^" . $pattern . "$#i", $uri)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                if (strpos($uri, 'ajax') === 0 || strpos($uri, 'agency') === 0) {
                    return $response->withStatus(403)->withJson([
                        "status"   => "error",
                        "messages" => ["شما به این بخش دسترسی ندارید"],
                    ]);
                }
                return $response->withStatus(302)->withHeader("location", baseUrl("/dashboard"));
            }
        }

        return $next($request, $response);
    }
}
