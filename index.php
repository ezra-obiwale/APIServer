<?php

function checkMethod($PROCESSOR, $method) {
    if (!method_exists($PROCESSOR, $method)) {
        throw new Exception('Not Implemented', 501);
    }
}

// TO ALLOW CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, X-Request-With, Request-With');
// CORS THINGIES ENDED

require_once 'bootstrap.php';

if ($INIT = config('global.init')) call_user_func($INIT);

// Default data processor
$DEFAULT_PROCESSOR = config('global.defaultProcessor');
// NodeToClass function
$NodeToClass = config('global.nodeToClass');
// Allowed request methods
$allowedMethods = config('global.allowedMethods');
$requestMethod = requestMethod();
$classMethod = config("global.requestMethods.{$requestMethod}");

// get path from GET parameters and into array
$path = filter_input(INPUT_GET, 'rstsvr__path');
// remove htaccess created variable
unset($_GET['rstsvr__path']);

// default status is true: expecting a successful action
$response = ['status' => true];

// get the target version
$version = getFirstPath($path);
// process request on filename
if (!$node = getFirstPath($path)) {
    $response = [
        'status' => false,
        'message' => 'Invalid path'
    ];
}
else {
    // check request method/type
    try {
        $NodeClass = $NodeToClass($version, $node);
        // Use node class if exists
        if (class_exists($NodeClass)) {
            $PROCESSOR = $NodeClass;
        }
        // Node class doesn't exist. Throw exception if it must exist to continue
        else if (config('global.nodes.appOnly')) {
            throw new Exception('Access denied', 403);
        }
        // Using default processor. Check if node is allowed
        else {
            $allowedNodes = config('global.nodes.allowed') ?: [];
            // Block if not allowed 
            if (count($allowedNodes) && !in_array($node, $allowedNodes)) {
                throw new Exception('Access denied', 403);
            }
            $PROCESSOR = $DEFAULT_PROCESSOR;
        }
        if (!is_a(new $PROCESSOR, Data::class)) {
            throw new Exception('Target class must implement class Data', 500);
        }
        // Check if method is allowed
        if ($allowedMethods && array_key_exists($node, $allowedMethods) && !in_array($_SERVER['REQUEST_METHOD'], $allowedMethods[$node])) {
            throw new \Exception('Method Not Allowed', 403);
        }
        // no processor matched
        else if (!$PROCESSOR) {
            throw new Exception('Server not properly set', 500);
        }
        // doing CRUD
        else {
            checkMethod($PROCESSOR, $classMethod);
            $options = [];
            $PROCESSOR::setNode($node);
            // process request
            switch (requestMethod()) {
                case 'GET':
                    $response['data'] = $PROCESSOR::get($path);
                    break;
                case 'POST':
                    $response['data'] = $PROCESSOR::create(filter_input_array(INPUT_POST), $path);
                    break;
                case 'PATCH':
                    $replace = config('global.replace.patch');
                    $options = ['replace' => !is_null($replace) ? $replace : false];
                case 'PUT':
                    if (!count($options)) {
                        $replace = config('global.replace.put');
                        $options = ['replace' => !is_null($replace) ? $replace : true];
                    }
                    $data = file_get_contents('php://input');
                    // fetch request data into $request_data
                    parse_str($data, $request_data);
                    $response['data'] = $PROCESSOR::update($path, $request_data, $options);
                    break;
                case 'DELETE':
                    $response['data'] = $PROCESSOR::delete($path);
                    break;
            }
        }
    }
    catch (Exception $ex) {
        if ($ex->getCode()) http_response_code($ex->getCode());
        $response['status'] = false;
        $response['message'] = $ex->getMessage();
    }
}
$PROCESSOR::output($response);
