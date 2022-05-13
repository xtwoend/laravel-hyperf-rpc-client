<?php

return [
    'consul' => [
        'host' => env('CONSUL_HOST', 'http://localhost:8500'),
        'token' => env('CONSUL_TOKEN')
    ]
];