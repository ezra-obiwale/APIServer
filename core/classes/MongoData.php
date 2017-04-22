<?php

use MongoDB\Client,
    MongoDB\Collection,
    MongoDB\Database,
    MongoDB\BSON\ObjectId,
    MongoDB\Operation\FindOneAndUpdate;

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
        $col = static::selectNode();
        static::preSave($data);
        $result = $col->insertOne($data);
        if ($result->getInsertedCount()) {
            static::postSave($data, (string) $result->getInsertedId());
            $data['_id'] = $result->getInsertedId();
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
        // get many
        if (!$id || is_array($id)) {
            // find by id or just retrieve all
            $params = $id ?: [];
            $options = [];
            // get search query
            if ($query = static::getSearchQuery()) {
                // get search keys
                $search = static::searchableKeys();
                if (!count($search))
                    return [];
                // regex to check if the keys contain the query
                $query = new \MongoDB\BSON\Regex($query, 'i');
                // update parameters with search keys
                foreach ($search as $key) {
                    $params['$or'][] = [$key => $query];
                }
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
            if ($sort = static::sortBy())
                $options['sort'] = $sort;
            // get projection keys
            $projection = static::setSearchResponseKeys();
            if (count($projection))
                $options['project'] = array_fill_keys($projection, 1);
            // return as array
            return $col->find($params, $options)->toArray();
        }
        // get one
        else {
            $id_parts = explode('/', $id);
            $id = array_shift($id_parts);
            $result = $col->findOne([
                '_id' => new ObjectId($id)
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
     * Updates a document/key
     * @param string|array $id If array, this is the criteria for the documents to update
     * @param mixed $data
     * @param array $options keys include upsert (boolean):FALSE and replace (boolean):FALSE
     * @return boolean
     */
    public static function update($id, $data, array $options = []) {
        unset($data['_id']);
        $col = static::selectNode();
        if (is_string($id)) {
            // allow update descendant keys
            if (stristr($id, '/', true)) {
                $paths = explode('/', $id);
                $id = array_shift($paths);
                $path = join('.', $paths);
                $data = [$path => $data];
            }
            // replace if only id is given
            else if (!array_key_exists('replace', $options)) {
                $options['replace'] = true;
            }
        }
        static::preSave($data, $id);

        $upsert = @$options['upsert'] ?: false;
        $method = 'findOneAndReplace';
        // determine the method to call
        if (!@$options['replace']) {
            $method = 'findOneAndUpdate';
            $data = [
                '$set' => $data
            ];
        }
        if ($data = $col->{$method}(is_array($id) ? $id : ['_id' => new ObjectId($id)]
                , $data, [
            'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            'upsert' => $upsert
                ])) {
            static::postSave($data, $id, true);
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
            if (!$data = static::get($id))
                return null;

            $_data = $data;
            foreach ($paths as $key => $path) {
                if (!$key)
                    $_data = &$data[$path];
                else
                    $_data = &$_data[$path];
                if (is_object($_data))
                    $_data = $_data->getArrayCopy();
            }
            $value = $_data[$last_key];
            unset($_data[$last_key]);
            if (static::update($id, $data))
                return $value;
        }
        else if ($resp = $col->findOneAndDelete(is_array($id) ? $id : ['_id' => new ObjectId($id)]))
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
     * @return Collection
     */
    final protected static function selectNode() {
        return static::db()->selectCollection(static::parseNodeName(static::getNode()));
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
     * @return string|null If string is returned, it is taken as an error message
     */
    protected static function preSave(&$data, $id) {
        
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
    protected static function validate(array $data, array $rules, array $messages = []) {
        return (new Validator($data, $rules, $messages))->run();
    }

    /**
     * Determines how documents should be sorted when fetching multiple
     * @return array Array of field keys and 1 (asc) or -1 (desc) as values. Multiple fields 
     * sorting is allowed
     */
    protected static function sortBy() {
        
    }

    /**
     * A list of keys to return when a search is done.
     * @return array
     */
    protected static function setSearchResponseKeys() {
        return [];
    }

}
