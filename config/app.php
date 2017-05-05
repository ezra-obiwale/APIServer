<?php

return [
    /*
     * List of request methods that are allowed on specified nodes
     * 
     * Keys are node names while values are array of request methods e.g. GET, PUT
     */
    'allowedMethods' => [],
    /*
     * Called to get the list of allowed domains
     * @var Callable 
     */
    'allowedDomains' => function() {
        // empty array indicates all
        return [];
    },
    /*
     * The processor class to use for saving token to the db
     */
    'authDataProcessor' => \V1\Auth::class,
    /*
     * The default processor class to use when options `appNodesOnly` is FALSE
     * and a class for the target node is not available
     */
    'defaultDataProcessor' => \Data\Mongo::class,
    /* The unique id of the app */
    'id' => '7d840828bcfe4ec56071d8850134c3d9',
    /*
     * Called when an endpoint is reached and before anything is done.
     * This is a good place to make any changes to default PHP settings
     * e.g. timezone
     * @var Callable
     */
    'init' => function($version, $node, $params) {
        date_default_timezone_set('Africa/Lagos');
        // allow get GET and login requests through
        if (!in_array(requestMethod(), ['GET']) && $node !== 'auth'
                && !\V1\Auth::verify())
                throw new Exception('Access denied!', 403);
    },
    /* Function to send mail with */
    'mailer' => function($to, $subject, $message, array $more = []) {
        $mail = new PHPMailer;

//        $mail->SMTPDebug = 3;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        // @IMPORTANT: Change
        $mail->Username = 'username@gmail.com';
        // @IMPORTANT: Change
        $mail->Password = 'password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        // @IMPORTANT: should remove this in production
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        // @IMPORTANT: Change
        $mail->From = 'noreply@restserver.com';
        // @IMPORTANT: Change
        $mail->FromName = 'The REST Server Team';

        if (is_array($to)) {
            $method = 'addAddress';
            foreach ($to as $key => $value) {
                if (is_string($key)) $mail->{$method}($key, $value);
                else $mail->{$method}($value);
                $method = 'addBCC';
            }
        }
        else $mail->addAddress($to);
        $mail->isHTML(TRUE);

        $mail->Subject = $subject;
        $mail->Body = $message;
        if ($more['plain']) $mail->AltBody = $more['plain'];
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        return true;
    },
    /*
     * Data\Mongo Class settings
     */
    'mongo' => [
        /*
         * The database name
         * @IMPORTANT: Change
         */
        'db' => 'restserver',
        /**
         * The key to hold resolved references in the result documents
         */
        'dataRefKey' => 'refs'
    ],
    // @IMPORTANT: Change
    'name' => 'RESTServer',
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
        return _toCamel(str_replace('-', '_', $version)) . '\\' . _toCamel(str_replace('-',
                                                                                       '_',
                                                                                       $node));
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
        /* Default methods applied to all endpoints */
        '*' => [
            'GET' => 'get',
            'PATCH' => 'patch',
            'POST' => 'post',
            'PUT' => 'put',
            'DELETE' => 'delete'
        ],
        'auth' => [
            'GET' => 'read'
        ]
    ],
    'urls' => [
        /*
         * The url where the rest server is hosted 
         * @IMPORTANT: Change
         */
        'server' => 'https://ezra-obiwale.github.com/RESTServer/',
        /* Client urls */
        'client' => [
            /*
             * Home page 
             * @IMPORTANT: Change
             */
            'home' => 'http://clientserver.com',
            /*
             * Link to the page where users can reset their passwords
             * It would be appended with GET variable token
             * i.e. ?token=ABCD1234
             * This should be returned with request to change the password 
             * 
             * @IMPORTANT: Change
             */
            'password_reset' => 'http://clientserver.com/reset-password',
            /*
             * Successful email verification 
             * @IMPORTANT: Change
             */
            'email_verification_success' => 'http://clientserver.com/email-verified',
            /*
             * Failed email verification 
             * @IMPORTANT: Change
             */
            'email_verification_failure' => 'http://clientserver.com/email-not-verified',
        ]
    ],
];
