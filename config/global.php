<?php

return [
    'defaultProcessor' => JsonData::class,
    'nodeToClass' => function($version, $node) {
        return _toCamel($version) . '\\' . _toCamel($node);
    },
    'mongo' => [
//        'db' => 'DB_NAME'
    ],
    'appNodesOnly' => true,
    'blockedNodes' => [],
    'allowedMethods' => []
];
