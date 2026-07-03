<?php
$sf = __DIR__ . '/status.json';
$qf = __DIR__ . '/queue.json';

if (!file_exists($sf)) {
    file_put_contents($sf, json_encode([
        "processing" => null,
        "completed" => []
    ]));
}

if (!file_exists($qf)) {
    file_put_contents($qf, json_encode([]));
}

$status = json_decode(file_get_contents($sf), true);
$queue = json_decode(file_get_contents($qf), true);

if (!is_array($status)) {
    $status = ["processing" => null, "completed" => []];
}

if (!is_array($queue)) {
    $queue = [];
}

$status['queue'] = $queue;

header('Content-Type: application/json');
echo json_encode($status);
