<?php
// Port 80 front page. Shows a harmless website so the server looks like an ordinary web host.
//  - "local" (default): a small built-in company website from the decoy/ folder (nothing leaves the server)
//  - an http(s) address: that site's page is fetched and shown
// The choice is saved by the panel (Settings) in panel/storage/fake_site.txt, so updates never reset it.

function curl_get_contents($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5", "Accept-Language: en-us,en;q=0.5"]);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$site = "local";
$cfg  = __DIR__ . "/panel/storage/fake_site.txt";
if (is_file($cfg)) {
    $v = trim((string) @file_get_contents($cfg));
    if ($v !== "") {
        $site = $v;
    }
}

if (preg_match('~^https?://~i', $site)) {
    echo curl_get_contents($site);
    exit;
}

$dir   = __DIR__ . "/decoy";
$pages = ["services", "about", "contact"];
$p     = isset($_GET["p"]) ? (string) $_GET["p"] : "";
$path  = parse_url(isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "/", PHP_URL_PATH);
header("Content-Type: text/html; charset=utf-8");
if (in_array($p, $pages, true)) {
    readfile($dir . "/" . $p . ".html");
} elseif ($p === "" && ($path === "/" || $path === "/index.php" || $path === "")) {
    readfile($dir . "/index.html");
} else {
    http_response_code(404);
    readfile($dir . "/404.html");
}
