<?php

return [
    /*
     * The default processor class to use when options `appNodesOnly` is FALSE
     * and a class for the target node is not available
     */
    'defaultProcessor' => JsonData::class,
    /*
     * Function to convert target nodes to their appropriate class names
     */
    'nodeToClass' => function($version, $node) {
        // CamelCase version as the namespace and CamelCase node as the class name
        return _toCamel(str_replace('-', '_', $version)) . '\\' . _toCamel(str_replace('-', '_', $node));
    },
    /*
     * MongoData Class settings
     */
    'mongo' => [
        /*
         * The database name
         */
        'db' => 'rest_server'
    ],
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
     * List of request methods that are allowed on specified nodes
     * 
     * Keys are node names while values are array of request methods e.g. GET, PUT
     */
    'allowedMethods' => []
];
