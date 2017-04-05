<?php

return [
    'dataProcessor' => JsonData::class,
    'mongo' => [
        'db' => 'MONGO_DB_NAME'
    ],
    'appNodesOnly' => true,
    'blockedNodes' => [],
    'allowedMethods' => [
    /*
     * Set allowed http methods for each node
     * If a node does not exist here, it is assumed that all methods are
     * allowed
     * 
     * Methods include GET, POST, PATCH, PUT, DELETE
     * 
     * 'nodeName' => ['GET']
     */
    ]
];
