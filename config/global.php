<?php

return [
    /*
     * List of request methods that are allowed on specified nodes
     * 
     * Keys are node names while values are array of request methods e.g. GET, PUT
     */
    'allowedMethods' => [],
    /*
     * The default processor class to use when options `appNodesOnly` is FALSE
     * and a class for the target node is not available
     */
    'defaultProcessor' => MongoData::class,
    /*
     * Called when an endpoint is reached and before anything is done.
     * This is a good place to make any changes to default PHP settings
     * e.g. timezone
     * @var Callable
     */
    'init' => function() {
        date_default_timezone_set('Africa/Lagos');
    },
    /*
     * MongoData Class settings
     */
    'mongo' => [
        /*
         * The database name
         */
        'db' => 'rest_server',
        /**
         * The key to hold resolved references in the result documents
         */
        'dataRefKey' => 'refs'
    ],
    /*
     * Nodes settings
     */
    'nodes' => [
        /*
         * Allow only nodes that have corresponding classes in the app directory
         */
        'appOnly' => true,
        /*
         * List of allowed nodes
         * 
         * This is only necessary if `appOnly` is FALSE. If none is specified,
         * then any node can be created on the database.
         */
        'allowed' => []
    ],
    /*
     * Function to convert target nodes to their appropriate class names
     */
    'nodeToClass' => function($version, $node) {
        // CamelCase version as the namespace and CamelCase node as the class name
        return _toCamel(str_replace('-', '_', $version)) . '\\' . _toCamel(str_replace('-', '_', $node));
    },
    /*
     * Indicates whether existing documents/rows should be replaced (i.e. overwritten)
     * when a patch or put request is received
     */
    'replace' => [
        /* Don't overwrite but update existing documents/rows */
        'patch' => false,
        /* Overwrite existing documents/rows with received data */
        'put' => true,
    ],
    /**
     * Class methods to call on different request methods
     */
    'requestMethods' => [
        'GET' => 'get',
        'PATCH' => 'update',
        'POST' => 'create',
        'PUT' => 'update',
        'DELETE' => 'delete'
    ]
];
