<?php

/**
 * Description of MongoData
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class MongoData extends JsonData {

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
     * Creates the data on the given node
     * @param array $data
     * @param string $id If not given, it is generated.
     * @return array|null
     */
    public static function create($data, $id = null) {
        // update existing document with descendant document at path
        if ($id && stristr($id, '/', true))
            return static::update($id, $data);
        // no id
        $data['_id'] = new MongoId();
        $id = (string) $data['_id'];
        $col = static::selectNode();
        if (is_string($error = static::preSave($data, $id, true))) {
            static::$error = $error;
            return false;
        }
        if ($col->insert($data)) {
            static::postSave($data, $id, true);
            return $data;
        }
        return false;
    }

    /**
     * 
     * @param type $id
     * @return MongoCursor
     */
    public static function get($id = null) {
        if ($response = static::external($id)) {
            return $response;
        }
        $col = static::selectNode();
        if (!$id || is_array($id)) {
            // get search query
            if ($query = static::getSearchQuery()) {
                // get search keys
                $search = static::searchableKeys();
                if (!count($search))
                    return [];
                // regex to check if the keys contain the query
                $query = new MongoRegex('/' . $query . '/i');
                // create the cursor
                $cursor = $col->find(['$or' => array_fill_keys($search, $query)]);
            }
            else // find by id or just retrieve all
                $cursor = $col->find($id ?: []);
            // get limit
            if ($limit = static::getLimit()) {
                // set limit
                $cursor = $cursor->limit((int) $limit);
                // get page index
                if ($page = static::getPageKey())
                // set starting point
                    $cursor = $cursor->skip(($page - 1) * $limit);
            }
            // get sort parameter
            if ($sort = static::sortBy())
                $cursor = $cursor->sort($sort);
            // return as array
            return iterator_to_array($cursor);
        }
        else {
            $id_parts = explode('/', $id);
            $id = array_shift($id_parts);
            $result = $col->findOne([
                '_id' => new MongoId($id)
            ]);
            foreach ($id_parts as $part) {
                if (!$result = $result[$part])
                    break;
            }
            return $result;
        }
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
    protected static function getLimit() {
        return filter_input(INPUT_GET, 'limit');
    }

    /**
     * Fetches the current page if paginating
     * @return string
     */
    protected static function getPageKey() {
        return filter_input(INPUT_GET, 'page');
    }

    /**
     * Updates a document/key
     * @param string|array $id If array, this is the criteria for the documents to update
     * @param mixed $data
     * @return boolean
     */
    public static function update($id, $data) {
        $col = static::selectNode();
        // allow update descendant keys
        if (!is_array($id) && stristr($id, '/', true)) {
            $paths = explode('/', $id);
            $id = array_shift($paths);
            $path = join('.', $paths);
            $data = [$path => $data];
        }
        if (is_string($error = static::preSave($data, $id))) {
            static::$error = $error;
            return false;
        }
        if ($data = $col->findAndModify(is_array($id) ? $id : ['_id' => new MongoId($id)]
                , ['$set' => $data], null, ['new' => true])) {
            static::postSave($data, $id);
            return $data;
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
            $data = static::get($id);
            $_data = $data;
            foreach ($paths as $key => $path) {
                if (!$key)
                    $_data = &$data[$path];
                else
                    $_data = &$_data[$path];
            }
            $value = $_data[$last_key];
            unset($_data[$last_key]);
            if (static::update($id, $data))
                return $value;
        }
        else if ($resp = $col->findAndModify(is_array($id) ? $id : ['_id' => new MongoId($id)]
                , null, null, ['remove' => true]))
            return $resp;
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
     * @return MongoCollection
     */
    final protected static function selectNode() {
        return static::db()->selectCollection(static::parseNodeName(static::$node));
    }

    /**
     * Creates the connection to the database
     * @param string $dbName If not provided, the default from the config file is used.
     * @return MongoDB
     */
    final protected static function db($dbName = null) {
        $mongo = new MongoClient();
        if (!self::$db)
            self::$db = $mongo->selectDB($dbName ?:
                    config('global', 'mongo', 'db'));
        return self::$db;
    }

    /**
     * Fetches a list of keys that search can be operated on
     * return array
     */
    protected static function searchableKeys() {
        return [];
    }

    /**
     * Called before create and update are called
     * @param mixed $data
     * @param string $id
     * @param boolean $new
     * @return string|null If string is returned, it is taken as an error message
     */
    protected static function preSave(&$data, $id, $new = false) {
        
    }

    /**
     * Called after create and update are called
     * @param mixed $data
     * @param string $id
     * @param boolean $new
     */
    protected static function postSave(&$data, $id, $new = false) {
        
    }

    /**
     * 
     * @param array $data Array of data to validate: field_name keys to values
     * @param array $rules Array of field_name keys to field rules values. Rules should be separated
     * by pipes (|)
     * @param array $messages Array of field_name keys to field error message values
     * @return string|null String of error message
     */
    protected static function validate(array $data, array $rules, array $messages = []) {
        try {
            return (new Validator($data, $rules, $messages))->run();
        }
        catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * Determines how documents should be sorted when fetching multiple
     * @return array Array of field keys and 1 (asc) or -1 (desc) as values. Multiple fields 
     * sorting is allowed
     */
    protected static function sortBy() {
        
    }

}
