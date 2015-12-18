<?php
    $client = new swoole_client(SWOOLE_TCP|SWOOLE_KEEP);
    $client->connect('127.0.0.1', 9501);
    $client->send('select * from test.user');
    echo $client->recv();