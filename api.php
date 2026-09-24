<?php
// Dati pubblici per la mappa: solo iniziative e contributi approvati, senza email.
require __DIR__ . '/inc/bootstrap.php';
session_write_close();

$t = tables();
$contributi = [];
foreach (read_rows('contributi') as $c) {
    if (is_true($c['approvata'])) {
        $contributi[$c['iniziativa_id']][] = array_intersect_key($c, array_flip($t['contributi']['public']));
    }
}
$out = [];
foreach (approved_iniziative() as $r) {
    $item = array_intersect_key($r, array_flip($t['iniziative']['public']));
    $item['id'] = $r['id'];
    $item['lat'] = (float)$r['lat'];
    $item['lng'] = (float)$r['lng'];
    $item['contributi'] = isset($contributi[$r['id']]) ? $contributi[$r['id']] : [];
    $out[] = $item;
}
header('Cache-Control: no-cache');
json_out([
    'iniziative' => $out,
    'contributi_generali' => isset($contributi['generale']) ? $contributi['generale'] : [],
]);
