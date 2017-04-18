<?php

return [
    'defaultProcessor' => JsonData::class,
    'nodeToClass' => function($version, $node) {
        return _toCamel($version) . '\\' . _toCamel($node);
    },
    'mongo' => [
        'db' => 'DATABASE_NAME'
    ],
    'appNodesOnly' => true,
    'blockedNodes' => [],
    'allowedMethods' => []
];
