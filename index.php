<?php

function checkMethod($PROCESSOR, $method) {
    if (!method_exists($PROCESSOR, $method)) {
        throw new Exception('Not Implemented', 404);
    }
}

// TO ALLOW CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, X-Request-With, Request-With');
// CORS THINGIES ENDED

require_once 'bootstrap.php';

// Default data processor
$PROCESSOR = config('global', 'defaultProcessor');
$NodeToClass = config('global', 'nodeToClass');
// Allowed request methods
$allowedMethods = config('global', 'allowedMethods');

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
        // Block if node is marked blocked 
        if (in_array($node, config('global', 'blockedNodes') ?: [])) {
            throw new Exception('Access denied', 403);
        }
        // Use node class if exists
        else if (class_exists($NodeClass)) {
            $PROCESSOR = $NodeClass;
        }
        // Node class doesn't exist. Throw exception if it must exist to continue
        else if (config('global', 'appNodesOnly')) {
            throw new Exception('Access denied', 403);
        }
        if (!is_a(new $PROCESSOR, Data::class)) {
            throw new Exception('Target class must implement class Data', 504);
        }
        // Check if method is allowed
        if ($allowedMethods && array_key_exists($node, $allowedMethods) &&
                !in_array($_SERVER['REQUEST_METHOD'], $allowedMethods[$node])) {
            throw new \Exception('Method Not Allowed', 403);
        }
        // no processor matched
        else if (!$PROCESSOR) {
            throw new Exception('Server not properly set', 504);
        }
        // doing CRUD
        else {
            // process request
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    checkMethod($PROCESSOR, 'get');
                    $response['data'] = $PROCESSOR::get($path);
                    break;
                case 'POST':
                    checkMethod($PROCESSOR, 'create');
                    $response['data'] = $PROCESSOR::create(filter_input_array(INPUT_POST), $path);
                    break;
                case 'PUT':
                case 'PATCH':
                    checkMethod($PROCESSOR, 'update');
                    $data = file_get_contents('php://input');
                    // fetch request data into $request_data
                    parse_str($data, $request_data);
                    $response['data'] = $PROCESSOR::update($path, $request_data);
                    break;
                case 'DELETE':
                    checkMethod($PROCESSOR, 'delete');
                    $response['data'] = $PROCESSOR::delete($path);
                    break;
            }
        }
    }
    catch (Exception $ex) {
        http_response_code($ex->getCode());
        $response['status'] = false;
        $response['message'] = $ex->getMessage();
    }
}
$PROCESSOR::output($response);
