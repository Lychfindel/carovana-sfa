<?php
// Foto dei contributi. Il pubblico vede solo quelle dei contributi approvati; l'admin tutte.
require __DIR__ . '/inc/bootstrap.php';
$is_admin = !empty($_SESSION['admin']);
session_write_close();

$cid = get_str('c');
$name = get_str('n');
if (!preg_match(RE_ID, $cid) || !preg_match(RE_FOTO, $name)) {
    http_response_code(404);
    exit;
}
if (!$is_admin) {
    $ok = false;
    foreach (read_rows('contributi') as $r) {
        if ($r['id'] === $cid) {
            $ok = is_true($r['approvata']);
            break;
        }
    }
    if (!$ok) {
        http_response_code(404);
        exit;
    }
}
$file = FOTO_DIR . '/' . $cid . '/' . $name;
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($is_admin ? 'private, no-store' : 'public, max-age=3600'));
readfile($file);
