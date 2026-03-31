<?php
header('Content-Type: text/plain; charset=utf-8');

$archivo = __DIR__ . '/inventario.json';
$datos = [
    [
        'prueba' => 'ok',
        'fecha' => date('c')
    ]
];

$json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json === false) {
    die("ERROR: json_encode falló\n");
}

$resultado = file_put_contents($archivo, $json);

if ($resultado === false) {
    die("ERROR: no se pudo escribir en $archivo\n");
}

echo "OK: se escribió correctamente en $archivo\n";
