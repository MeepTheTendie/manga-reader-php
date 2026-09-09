<?php
/** Local development stays passwordless; a remote web server fails closed. */
function protectManga(): void {
    if (PHP_SAPI === 'cli') return;
    $host = strtolower(parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '');
    $local = PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
        && in_array($host, ['localhost','127.0.0.1','[::1]'], true);
    $hash = getenv('MANGA_PASSWORD_HASH') ?: '';
    if (!$local || $hash !== '') {
        if ($hash === '') { http_response_code(503); exit('Configure MANGA_PASSWORD_HASH before remote access.'); }
        if (!password_verify($_SERVER['PHP_AUTH_PW'] ?? '', $hash)) {
            usleep(250000);
            header('WWW-Authenticate: Basic realm="Private Manga Reader", charset="UTF-8"');
            http_response_code(401); exit('Authentication required.');
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $originHost = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        $originPort = $origin ? parse_url($origin, PHP_URL_PORT) : null;
        $requestPort = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_PORT);
        $crossSite = ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site';
        if ($crossSite || ($origin && (strtolower($originHost ?: '') !== $host || $originPort !== $requestPort))) {
            http_response_code(403); exit('Cross-site writes are not allowed.');
        }
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            http_response_code(415); exit('Use application/json.');
        }
    }
    header('Cache-Control: private, no-store');
}
