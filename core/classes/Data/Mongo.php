<?php

namespace Data;

use Exception,
    MongoDB\BSON\ObjectId,
    MongoDB\Client,
    MongoDB\Collection,
    MongoDB\Database,
    MongoDB\Operation\FindOneAndUpdate,
    Validator;
use function config;

/**
 * Description of Mongo
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Mongo extends Json {

    /**
     * Array of indexed nodes
     * @var array
     */
    private static $indexed = [];

    /**
     * The error that occured during the operation
     * @var string
     */
    protected static $error;

    /**
     *
     * @var MongoDB
     */
    private static $db;

    /**
     * Array of key names to resolved reference array
     * @var array
     */
    private static $resolvedRefs = [];

    /**
     * Creates the data on the given node
     * @param array $data
     * @param string $id If not given, it is generated.
     * @return array|null
     */
    public static function create($data, $id = null) {
        // update existing document with descendant document at path
        if ($id && stristr($id, '/', true)) return static::update($id, $data);
        // no id
        $col = static::selectNode();
        static::preSave($data);
        $data['created'] = time();
        $result = $col->insertOne($data);
        if ($result->getInsertedCount()) {
            static::postSave($data, (string) $result->getInsertedId());
            $data['_id'] = $result->getInsertedId();
            return self::resolveRefs($data);
        }
        return false;
    }

    /**
     * https://docs.mongodb.com/manual/tutorial/query-documents/
     * @param string|array $id
     * @param boolean|array $projection https://docs.mongodb.com/manual/tutorial/project-fields-from-query-results/
     * @return MongoCursor
     */
    public static function get($id = null, $projection = false) {
        if ($response = static::external($id)) {
            return $response;
        }
        $col = static::selectNode();
        // get many
        if (!$id || is_array($id)) {
            // find by id or just retrieve all
            $params = $id ?: [];
            $options = [];
            // get search query
            if ($query = static::getSearchQuery()) {
                // get search keys
                $search = array_keys(static::searchableKeys());
                if (!count($search)) return [];
                // regex to check if the keys contain the query
                $query = new \MongoDB\BSON\Regex($query, 'i');
                // update parameters with search keys
                foreach ($search as $key) {
                    $params['$or'][] = [$key => $query];
                }
                // get projection keys
                $_projection = static::setSearchResponseKeys();
                if (count($_projection))
                        $projection = array_fill_keys($_projection, 1);
            }
            // get limit
            if ($limit = static::setLimit()) {
                // set limit
                $options['limit'] = (int) $limit;
                // get page index
                if ($page = static::setPageKey())
                // set starting point
                    $options['skip'] = ($page - 1) * $limit;
            }
            // get sort parameter
            if ($sort = static::sortBy()) $options['sort'] = $sort;
            if ($projection && count($projection))
                    $options['projection'] = $projection;
            // return as array
            return self::resolveRefs($col->find($params, $options)->toArray(),
                                                true);
        }
        // get one
        else {
            $id_parts = explode('/', $id);
            $id = array_shift($id_parts);
            if (count($projection))
                    $options['projection'] = array_fill_keys($projection, 1);
            $result = $col->findOne([
                '_id' => new ObjectId($id)
            ]);
            foreach ($id_parts as $part) {
                if (!$result = $result[$part]) break;
            }
            return $result ? self::resolveRefs((array) $result) : $result;
        }
    }

    /**
     * Resolves all the references in the result
     * @param array $result
     * @param boolean $many Indicates that the $result contains several documents
     * @return array
     */
    final protected static function resolveRefs($result, $many = false) {
        if (is_array($result) && is_array($pull = static::pullRefs())) {
            foreach ($pull as $key) {
                $nodeName = static::FKToNode($key);
                if (!$many) {
                    self::resolveRef($key, $result, $nodeName);
                }
                else {
                    foreach ($result as &$res) {
                        if (is_object($res)) {
                            $res = method_exists($res, 'toArray') ?
                                    $res->toArray() : $res->getArrayCopy();
                        }
                        self::resolveRef($key, $res, $nodeName);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Resolves a key reference on the given node name
     * @param string $key
     * @param array $result
     * @param string $nodeName
     */
    final protected static function resolveRef($key, array &$result, $nodeName) {
        // reference doesn't exist in result; nothing to do here.
        if (!array_key_exists($key, $result)
                // only continue if value exists
                || empty($result[$key])) return;

        $refValue = $result[$key];
        // only get reference if not already gotten
        if (!array_key_exists($key, self::$resolvedRefs)
                || !array_key_exists($refValue, self::$resolvedRefs[$key])) {
            global $NodeToClass, $DEFAULT_PROCESSOR;
            // get app node class
            $NodeClass = $NodeToClass(self::getNamespace(), $nodeName);
            // use default processor if app node class doesn't exist
            if (!class_exists($NodeClass)) $NodeClass = $DEFAULT_PROCESSOR;
            // store current class refs
            $refs = self::$resolvedRefs;
            // empty refs
            self::$resolvedRefs = [];
            // set the node name
            $NodeClass::setNode($nodeName);
            // resolve ref
            $resolved = $NodeClass::selectNode()->find([
                '_id' => [
                    '$in' => array_map(function($id) {
                                return self::createGUID($id);
                            }, (array) $refValue),
                ]
            ]);
            // restore current class refs
            self::$resolvedRefs = $refs;
            // add resolved ref
            if (is_string($refValue))
                    self::$resolvedRefs[$key][$refValue] = $resolved->toArray();
            else {
                if (!array_key_exists($key, self::$resolvedRefs))
                        self::$resolvedRefs[$key] = $resolved->toArray();
                else
                        self::$resolvedRefs = array_merge(self::$resolvedRefs,
                                                          $resolved->toArray());
            }
        }
        // add the reference to the result
        $result[config('app.mongo.dataRefKey')][$key] = is_string($refValue) ?
                // requires only one document
                self::$resolvedRefs[$key][$refValue][0] :
                // requires multiple documents
                self::$resolvedRefs[$key];
    }

    /**
     * Fetches the search query
     * @return string
     */
    protected static function getSearchQuery() {
        return filter_input(INPUT_GET, 'query');
    }

    /**
     * Fetches limit for the query
     * @return string
     */
    protected static function setLimit() {
        return filter_input(INPUT_GET, 'limit');
    }

    /**
     * Fetches the current page if paginating
     * @return string
     */
    protected static function setPageKey() {
        return filter_input(INPUT_GET, 'page');
    }

    /**
     * Determines how documents should be sorted when fetching multiple
     * @return array Array of field keys and 1 (asc) or -1 (desc) as values. Multiple fields 
     * sorting is allowed
     */
    protected static function sortBy() {
        return filter_input(INPUT_GET, 'sort');
    }

    /**
     * Updates a document/key
     * @param string|array $id If array, this is the criteria for the documents to update
     * @param mixed $data
     * @param array $options keys include upsert (boolean):FALSE and replace (boolean):FALSE
     * @return boolean
     */
    public static function update($id, $data, array $options = []) {
        // remove existing primary key
        unset($data['_id']);
        // remove existing references
        unset($data[config('app.mongo.dataRefKey')]);
        $col = static::selectNode();
        $descendant = false;
        if (is_string($id)) {
            // allow update descendant keys
            if (stristr($id, '/', true)) {
                $paths = explode('/', $id);
                $id = array_shift($paths);
                $path = join('.', $paths);
                $data = [$path => $data];
                $descendant = true;
            }
            // replace if only id is given
            else if (!array_key_exists('replace', $options)) {
                $options['replace'] = true;
            }
        }
        if (!$descendant) $data['updated'] = time();
        static::preSave($data, $id, $options);

        $upsert = @$options['upsert'] ?: false;
        $method = 'findOneAndReplace';
        // determine the method to call
        if (!$options['replace']) {
            $method = 'findOneAndUpdate';
            $data = [
                '$set' => $data
            ];
        }
        if ($data = $col->{$method}(is_array($id) ? $id : ['_id' => new ObjectId($id)]
                , $data,
                                                                                 [
            'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            'upsert' => $upsert
                ])) {
            static::postSave($data, $id, true);
            return self::resolveRefs((array) $data);
        }
        return false;
    }

    /**
     * Deletes a document/key
     * @param string|array $id If array, this is the criteria for the documents to delete
     * @return boolean}array
     */
    public static function delete($id = null) {
        $col = static::selectNode();
        // allow update descendant keys
        if (is_string($id) && stristr($id, '/', true)) {
            $paths = explode('/', $id);
            $id = array_shift($paths);
            $last_key = array_pop($paths);
            if (!$data = static::get($id)) return null;

            $_data = $data;
            foreach ($paths as $key => $path) {
                if (!$key) $_data = &$data[$path];
                else $_data = &$_data[$path];
                if (is_object($_data)) $_data = $_data->getArrayCopy();
            }
            $value = $_data[$last_key];
            unset($_data[$last_key]);
            if (static::update($id, $data)) return $value;
        }
        $method = is_array($id) ? 'deleteMany' : 'findOneAndDelete';
        if ($resp = $col->{$method}(is_array($id) ? $id : ['_id' => new ObjectId($id)]))
                return is_array($id) ?: $resp;
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function output($response) {
        if ($response['data'] === false) {
            unset($response['data']);
            $response['message'] = static::$error ?: 'Operation failed!';
        }
        parent::output($response);
    }

    /**
     * Selects the collection (node)
     * @return Collection
     */
    final protected static function selectNode() {
        $nodeName = static::parseNodeName(static::getNode());
        $collection = static::db()->selectCollection($nodeName);
        // create indexes if not already created
        if (!in_array($nodeName, self::$indexed)) {
            $unique = static::uniqueKeys();
            $compound = array_diff_key(static::searchableKeys(), $unique);
            $indexes = [];
            foreach ($unique as $key => $dir) {
                $indexes[] = ['key' => [$key => $dir], 'unique' => true];
            }
            foreach ($compound as $key => $dir) {
                $indexes[] = ['key' => [$key => $dir]];
            }

            if (count($indexes)) $collection->createIndexes($indexes);
            self::$indexed[] = $nodeName;
        }
        return $collection;
    }

    /**
     * The unique keys on the node
     * @return array [key => 1 | -1, ...]
     */
    protected static function uniqueKeys() {
        return [];
    }

    /**
     * Creates the connection to the database
     * @param string $dbName If not provided, the default from the config file is used.
     * @return Database
     */
    final protected static function db($dbName = null) {
        $mongo = new Client();
        if (!self::$db)
                self::$db = $mongo->selectDatabase($dbName ?:
                    config('app.mongo.db'));
        return self::$db;
    }

    /**
     * Fetches a list of keys that search can be operated on
     * return array [key => 1 | -1, ...]
     */
    protected static function searchableKeys() {
        return [];
    }

    /**
     * Called before create and update are called
     * @param mixed $data
     * @param string $id Only provided on update and has the id of the document
     * to update
     * @param array $options Only provided on update and is the options passed 
     * into update method
     * @return string|null If string is returned, it is taken as an error message
     */
    protected static function preSave(&$data, $id = null, array $options = []) {
        
    }

    /**
     * Called after create and update are called
     * @param mixed $data
     * @param string $id
     * @param boolean $isUpdate
     */
    protected static function postSave($data, $id, $isUpdate = false) {
        
    }

    /**
     * 
     * @param array $data Array of data to validate: field_name keys to values
     * @param array $rules Array of field_name keys to field rules values. Rules should be separated
     * by pipes (|)
     * @param array $messages Array of field_name keys to field error message values
     * @return string|null String of error message
     */
    protected static function validate(array $data, array $rules,
                                       array $messages = []) {
        return (new Validator($data, $rules, $messages))->run();
    }

    /**
     * A list of keys to return when a search is done.
     * @return array
     */
    protected static function setSearchResponseKeys() {
        return [];
    }

    /**
     * Creates a Mongo ObjectId
     * @param string $base
     * @return ObjectId
     */
    public static function createGUID($base = null) {
        return new ObjectId($base);
    }

    /**
     * Indicates the references to pull when fetching documents
     * @return array An array of document keys to pull e.g. user_id
     */
    protected static function pullRefs() {
        return false;
    }

    public static function exceptions(Exception $ex) {
        $message = $ex->getMessage();
        // handle duplication error
        if (substr($ex->getMessage(), 0, 6) === 'E11000') {
            preg_match('~"(.*)"~', $ex->getMessage(), $match);
            $message = $match[1] . ' already exists!';
        }
        return $message;
    }

}
