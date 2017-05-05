<?php

use Data\Data;

require_once 'bootstrap.php';

// TO ALLOW CORS
try {
    $allowedDomains = config('app.allowedDomains');
    $allowed = false;
    foreach ($allowedDomains() as $domain) {
        header("Access-Control-Allow-Origin: $domain", false);
        $allowed = true;
    }
    // no allowed domains specified: allow all
    if (!$allowed) {
        header("Access-Control-Allow-Origin: *");
    }
    header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, X-Request-With, Request-With');
}
catch (Exception $ex) {
    http_response_code('501');
}
// CORS THINGIES ENDED
// Default data processor
$DEFAULT_PROCESSOR = config('app.defaultDataProcessor');
// NodeToClass function
$NodeToClass = config('app.nodeToClass');

// get path from GET parameters and into array
$path = filter_input(INPUT_GET, 'rstsvr__path');
// remove htaccess created variable
unset($_GET['rstsvr__path']);
// get the target version
$version = getFirstPath($path);
// process request on filename
$node = getFirstPath($path);

// Allowed request methods
$allowedMethods = config("app.allowedMethods.{$node}");
$requestMethod = requestMethod();
if (!$classMethod = config("app.requestMethods.{$node}.{$requestMethod}"))
        $classMethod = config("app.requestMethods.*.{$requestMethod}");

// default status is true: expecting a successful action
$response = ['status' => true];

if (!$node) {
    $response = [
        'status' => false,
        'message' => 'Invalid path'
    ];
}
else {
    try {
        // initialize app
        if ($INIT = config('app.init'))
                call_user_func_array($INIT, [$version, $node, explode('/', $path)]);
        $NodeClass = $NodeToClass($version, $node);
        // Use node class if exists
        if (class_exists($NodeClass)) {
            $PROCESSOR = $NodeClass;
        }
        // Node class doesn't exist. Throw exception if it must exist to continue
        else if (config('app.nodes.appOnly')) {
            throw new Exception('Access denied', 403);
        }
        // Using default processor. Check if node is allowed
        else {
            $allowedNodes = config('app.nodes.allowed') ?: [];
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
        if (is_array($allowedMethods) && !in_array($_SERVER['REQUEST_METHOD'], $allowedMethods[$node])) {
            throw new Exception('Method Not Allowed', 403);
        }
        // no processor matched
        else if (!$PROCESSOR) {
            throw new Exception('Server not properly set', 500);
        }
        // doing CRUD
        else {
            // check request method/type
            if (!method_exists($PROCESSOR, $classMethod)) {
                throw new Exception('Not Implemented', 501);
            }
            $options = [];
            $PROCESSOR::setNode($node);
            // process request
            switch ($requestMethod) {
                case 'GET':
                    $response['data'] = $PROCESSOR::{$classMethod}($path);
                    break;
                case 'POST':
                    $response['data'] = $PROCESSOR::{$classMethod}(filter_input_array(INPUT_POST), $path);
                    break;
                case 'PATCH':
                    $replace = config('app.replace.patch');
                    $options = ['replace' => !is_null($replace) ? $replace : false];
                    $data = file_get_contents('php://input');
                    // fetch request data into $request_data
                    parse_str($data, $request_data);
                    $response['data'] = $PROCESSOR::{$classMethod}($path, $request_data, $options);
                    break;
                case 'PUT':
                    $replace = config('app.replace.put');
                    $options = ['replace' => !is_null($replace) ? $replace : true];
                    $data = file_get_contents('php://input');
                    // fetch request data into $request_data
                    parse_str($data, $request_data);
                    $response['data'] = $PROCESSOR::{$classMethod}($path, $request_data, $options);
                    break;
                case 'DELETE':
                    $response['data'] = $PROCESSOR::{$classMethod}($path);
                    break;
            }
        }
    }
    catch (Exception $ex) {
        if ($ex->getCode()) http_response_code($ex->getCode());
        if (!$PROCESSOR) $PROCESSOR = $DEFAULT_PROCESSOR;
        $message = $PROCESSOR::exceptions($ex) ?: $ex->getMessage();
        $response['status'] = false;
        $response['message'] = $message;
    }
}
$PROCESSOR::output($response);
