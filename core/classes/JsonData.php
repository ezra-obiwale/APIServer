<?php

/**
 * Description of JsonData
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class JsonData implements Data {

    /**
     * The name of the node being operated on
     * @var string
     */
    protected static $node;

    /**
     * Sets the node to work on
     * @param string $node
     */
    public static function setNode($node) {
        static::$node = $node;
    }

    /**
     * 
     * @param mixed $data
     * @param string $id
     * @return array|null
     */
    public static function create($data, $id = null) {
        // creating a new document|object entirely
        if (!$id) {
            // ensure object has id
            $data['id'] = @$data['id'] ?: static::createGUID();
            // use id as the path
            $id = $data['id'];
        }
        // save data to the path on the node
        $updatedNodeData = static::setDataAtPath($data, $id, static::getNodeData());
        // save updated node data to file
        static::saveDataToNode($updatedNodeData);
        // return given data
        return $data;
    }

    /**
     * Fetches the data on the node at the given path
     * @param string $id
     * @return mixed
     */
    public static function get($id = null) {
        if ($resp = static::external($path)) return $resp;
        // get data at node
        $data = static::getNodeData();
        // get data at id
        return static::getDataAtPath($data, $id);
    }

    /**
     * Updates the data on the node at the given path
     * @param string $id
     * @param mixed $data
     * @return mixed
     */
    public static function update($id, $data) {
        $newData = static::setDataAtPath($data, $id, static::getNodeData(), false);
        static::saveDataToNode($newData);
        return $data;
    }

    /**
     * Deletes data at path on node
     * @param string $path
     * @return null
     */
    public static function delete($path = null) {
        // delete path on node
        if ($path) {
            // get data at path
            $data = static::getDataAtPath(static::getNodeData(), $path, true);
            // get last key from id
            $paths = explode('/', $path);
            $last_key = null;
            while (!$last_key && count($paths))
                $last_key = array_pop($paths);
            if ($last_key && array_key_exists($last_key, $data)) {
                // remove data at last key from general data
                unset($data[$last_key]);
                // save general data
                static::saveDataToNode($data);
            }
        }
        // delete node itstatic
        else {
            // get file path
            $file_path = static::getFilePath();
            // delete file
            unlink($file_path);
        }
        return null;
    }

    /**
     * Sends the response out to the screen
     * @param mixed $response
     */
    public static function output($response) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit(0);
    }

    /**
     * Fetches the data at the given node
     * @return array
     */
    private static function getNodeData() {
        if (FALSE === $file_path = static::getFilePath()) return null;
        return json_decode(@file_get_contents($file_path), true) ?: [];
    }

    /**
     * Fetches the full path to the node file
     * @param boolean $checkExists
     * @return string|boolean
     */
    private static function getFilePath($checkExists = false) {
        $file_path = DATA . static::getNode() . '.json';
        return $checkExists && !is_readable($file_path) ? false : $file_path;
    }

    /**
     * Sets the data to the given path on the old data
     * @param mixed $newData
     * @param string $path
     * @param array $oldData
     * @param boolean $overwrite Indicates whether to overwrite or merge with existing data, if any
     * @return array
     */
    private static function setDataAtPath($newData, $path, $oldData, $overwrite =
    true) {
        $location = & $oldData;
        foreach (explode('/', $path) as $p) {
            if (!$p) continue;
            if (!@$location[$p]) $location[$p] = NULL;
            $location = & $location[$p];
        }
        $location = $overwrite ? $newData : array_merge($location, $newData);
        return $oldData;
    }

    /**
     * Fetches the data at the given path and on the given data
     * @param array $data
     * @param string $path
     * @param boolean $returnParent Indicates whether to return the parent object of the path 
     * instead of the path itstatic
     * @return mixed
     */
    private static function getDataAtPath($data, $path, $returnParent = false) {
        $paths = explode('/', $path);
        $last_key = array_pop($paths);
        while (!$last_key && count($paths))
            $last_key = array_pop($paths);
        foreach ($paths as $p) {
            if (!$p) continue;
            $data = @$data[$p];
            if (!$data) break;
        }
        return !$returnParent && $last_key ? @$data[$last_key] : $data;
    }

    /**
     * Creates a globally unique 36 character id
     */
    public static function createGUID() {
        if (function_exists('com_create_guid')) {
            return substr(com_create_guid(), 1, 36);
        }
        else {
            mt_srand((double) microtime() * 10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid = substr($charid, 0, 8) . $hyphen .
                    substr($charid, 8, 4) . $hyphen .
                    substr($charid, 12, 4) . $hyphen .
                    substr($charid, 16, 4) . $hyphen .
                    substr($charid, 20, 12);

            return $uuid;
        }
    }

    /**
     * Saves the given data to the given node
     * @param array $data
     */
    private static function saveDataToNode($data) {
        file_put_contents(static::getFilePath(), json_encode($data));
    }

    /**
     * Fetches the namespace of the called class
     * @return string
     */
    final protected static function getNamespace() {
        $classNameParts = explode('\\', get_called_class());
        array_pop($classNameParts);
        return join('\\', $classNameParts);
    }

    /**
     * Process extended GET requests
     * Ex. countries/id loads a given country
     * countries/id/states should load states/?countries=id
     * @param string $id
     * @return mixed
     */
    final protected static function external($id) {
        if (is_array($id)) return;
        $parts = explode('/', $id);
        if ($parts < 2) return;
        $rawClass = array_pop($parts);
        // the last part of the path is the target class
        $targetClass = _toCamel(str_replace('-', '_', $rawClass));
        // target class should have the same namespace as current class
        $fullClassName = static::getNamespace() . '\\' . $targetClass;
        if (!class_exists($fullClassName)) return;
        $key = null;
        $fks = static::nodeToFK(static::getNode(), $rawClass);
        $val = array_shift($parts);
        if (is_array($fks)) {
            $or = [];
            foreach ($fks as $fk) {
                $or[] = [$fk => $val];
            }
            // params for target class
            $params = ['$or' => $or];
        }
        else {
            $params = [$fks => $val];
        }
        // add other path parts to params
        while (count($parts)) {
            $next = array_shift($parts);
            if (!$key) {
                $key = $next;
            }
            else {
                $params[static::nodeToFK($key)] = $next;
                $key = null;
            }
        }
        return $fullClassName::get($params);
    }

    /**
     * Converts a node name to a string that can be used as a foreign key on 
     * other nodes
     * @param string $node
     * @param string $target The target node which holds the FK
     * @return string|array If array, array of strings representing the possible
     * foreign keys
     */
    protected static function nodeToFK($node, $target) {
        return static::parseNodeName($node) . '_id';
    }

    /**
     * Converts a foreign key string to its corresponding node name
     * @param string $fk
     * @return string
     */
    protected static function FKToNode($fk) {
        $parts = explode('_', $fk);
        if (count($parts) > 1) array_pop($parts);
        return join('_', $parts);
    }

    /**
     * Parses the node name received from request to format to use in thee database
     * @param string $node
     * @return string
     */
    protected static function parseNodeName($node) {
        return $node;
    }

    /**
     * Fetches the target node
     * @return string
     */
    protected static function getNode() {
        if (!$namespace = static::getNamespace()) {
            return static::$node;
        }
        return camelTo_(substr(get_called_class(), strlen($namespace) + 1));
    }

}
