<?php
// Samo za lokalni razvoj (npm run dev). Na produkciji isto radi .htaccess.
declare(strict_types=1);

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

const CSP = "default-src 'self'; script-src 'self' https://connect.facebook.net; "
    . "img-src 'self' data: https://www.facebook.com; "
    . "connect-src 'self' https://www.facebook.com https://connect.facebook.net; "
    . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

header('Content-Security-Policy: ' . CSP);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$blocked = '#^/(\.git|\.htaccess|\.gitignore|docs|tests|scripts|dev|storage|api/lib|api/config\.example\.php|package\.json|README\.md)(/|$)#';
if (preg_match($blocked, $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$pages = [
    '/' => 'index.html',
    '/hvala' => 'hvala.html',
    '/politika-privatnosti' => 'politika-privatnosti.html',
    '/uslovi-koriscenja' => 'uslovi-koriscenja.html',
];

if (isset($pages[$path])) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/' . $pages[$path]);
    return true;
}

if (preg_match('#^/(index|hvala|politika-privatnosti|uslovi-koriscenja)\.html$#', $path, $m)) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    $target = $m[1] === 'index' ? '/' : '/' . $m[1];
    header('Location: ' . $target . ($query ? '?' . $query : ''), true, 301);
    return true;
}

if ($path === '/api/submit.php') {
    require $root . '/api/submit.php';
    return true;
}

if (str_ends_with($path, '.php')) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

return false;
