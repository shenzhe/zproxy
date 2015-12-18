<?php

$opt = getopt("d", [
    "ip::",
    "port::",
    "pool::"
]);
if (empty($opt['ip']) || empty($opt['port']) || empty($opt['pool'])) {
    echo "examples:  php proxy.php --ip=0.0.0.0 --port=9501 --pool=4 -d" . PHP_EOL;
    return;
}
if(!extension_loaded('swoole')) {
    echo 'pls install swoole extension, url: https://github.com/swoole/swoole-src'.PHP_EOL;
    return;
}
$config = null;
$pdo = null;
$serv = new swoole_server($opt['ip'], $opt['port']);
$daemonize = 0;
if(isset($opt['d'])) {
    $daemonize = 1;
}
$serv->set([
    'worker_num'=>4,
    'task_worker_num'=>$opt['pool'],
    'daemonize' => $daemonize
]);

$serv->on('start', function($serv) {
    global $opt;
    swoole_set_process_name("mysql proxy runing tcp://{$opt['ip']}:{$opt['port']}, start:".date("Y-m-d H:i:s").", pid:".$serv->master_pid);
});

$serv->on('managerStart', function($serv) {
   swoole_set_process_name("mysql proxy manager process, pid:".$serv->manager_pid);
});

$serv->on('workerStart', function($serv, $workerId) {
    if($workerId <4) { //worker id
        swoole_set_process_name("mysql proxy worker process, pid:".$serv->worker_id);
    } else {
        swoole_set_process_name("mysql proxy pool process, pid:".$serv->worker_id);
        global $config;
        $config = include_once(__DIR__.DIRECTORY_SEPARATOR.'config.php');
        include_once(__DIR__.DIRECTORY_SEPARATOR.'zpdo.php');
        global $pdo;
        $pdo = new zpdo($config);

    }
});

$serv->on('receive', function($serv, $fd, $fromId, $data) {
    if (empty($data)) {
        return;
    }
    $serv->task([$fd, $data]);  //数据转到task进行处理
});

$serv->on('task', function($serv, $taskId, $fromId, $_data) {
    list($fd, $sql) = $_data;
    swoole_set_process_name("mysql proxy pool process, pid:".$serv->worker_id." sql:{$sql}");
    global $pdo;
    $ret = $pdo->fetchBySql($sql);
    $serv->send($fd, json_encode($ret));
    swoole_set_process_name("mysql proxy pool process, pid:".$serv->worker_id);
});

$serv->on('finish', function() {});

$serv->start();
