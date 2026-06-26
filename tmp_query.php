<?php
require 'modelo/conexion.php';
$db = (new Conexion())->conectar();

// Verificar los CPs del screenshot que no muestran barrio
$cps = ['GT0077', 'GT0070', 'GT8550', 'GT1011'];
echo "=== CPs del screenshot SIN barrio ===\n";
foreach ($cps as $cp) {
    $rows = $db->query("
        SELECT cp.id, cp.codigo_postal, cp.id_departamento, d.nombre as depto, 
               cp.id_municipio, m.nombre as muni, 
               cp.id_barrio, b.nombre as barrio
        FROM codigos_postales cp
        LEFT JOIN departamentos d ON d.id = cp.id_departamento
        LEFT JOIN municipios m ON m.id = cp.id_municipio
        LEFT JOIN barrios b ON b.id = cp.id_barrio
        WHERE cp.codigo_postal = '$cp'
        ORDER BY cp.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "\n  CP: $cp → " . count($rows) . " registro(s)\n";
    foreach ($rows as $r) {
        echo "    ID:{$r['id']} | {$r['depto']} / {$r['muni']} / " . ($r['barrio'] ?? '(SIN BARRIO)') . "\n";
    }
}

// ¿Cuántos CPs en total tienen UNA sola entrada y esa entrada NO tiene barrio?
$sinBarrioUnicos = $db->query("
    SELECT COUNT(*) FROM codigos_postales
    WHERE (id_barrio IS NULL OR id_barrio = 0)
")->fetchColumn();
echo "\n=== Total CPs sin barrio (después de limpieza): $sinBarrioUnicos ===\n";

// ¿De qué países son?
echo "\n=== CPs sin barrio por país ===\n";
$porPais = $db->query("
    SELECT p.nombre, COUNT(*) as cnt
    FROM codigos_postales cp
    LEFT JOIN paises p ON p.id = cp.id_pais
    WHERE (cp.id_barrio IS NULL OR cp.id_barrio = 0)
    GROUP BY cp.id_pais
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($porPais as $pp) {
    echo "  {$pp['nombre']}: {$pp['cnt']} CPs sin barrio\n";
}
