<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

// Channel Server.
$channel_server = new Channel\Server('0.0.0.0', 2206);

// Websocket server
$worker = new Worker("websocket://0.0.0.0:8000");
$worker->uidConnections = [];

// 4 processes
$worker->count = 4;

$worker->onWorkerStart = function ($worker) {
    // Channel client - connect current worker to Channel Server.
    Channel\Client::connect('127.0.0.1', 2206);
    // Subscribe worker to broadcast event .
    Channel\Client::on('broadcast', function ($data) use ($worker) {

        $oData = json_decode($data);
        if (!empty($oData->uid)) {
            if (isset($worker->uidConnections[$oData->uid])) {
                $uidConnection = $worker->uidConnections[$oData->uid];
                $uidConnection->send($oData->msg);
            }
        } else {
            foreach ($worker->uidConnections as $uidConnection)
            {
                $uidConnection->send($oData->msg);
            }
        }
    });
    // you can subscribe any events you want.
    Channel\Client::on('some other event like send group', function ($data) use ($worker) {
        // Your send to group business.
    });
};


// Emitted when new connection come, add uid to connection's params
$worker->onConnect = function ($connection) use ($worker) {
    $connection->onWebSocketConnect = function ($connection, $header) use ($worker) {
        $connection->uid = $_GET['uid'];
        $worker->uidConnections[$connection->uid] = $connection;
        echo "New connection: user $connection->uid\n";
    };
};

// Emitted when data received, send to broadcast for all workers
$worker->onMessage = function ($connection, $data) use ($worker) {
    // Publish broadcast event to all worker processes.
    Channel\Client::publish('broadcast', $data);
};

// Emitted when connection closed
$worker->onClose = function ($connection) use ($worker) {
    if (isset($connection->uid)) {
        unset($worker->uidConnections[$connection->uid]);
    }
    echo "Connection closed\n";
};

// Run worker
Worker::runAll();