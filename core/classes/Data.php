<?php

/**
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
interface Data {

    /**
     * Sets the target node
     */
    public static function setNode($node);
    
    /**
     * Creates the given data at the given node and id, if given
     * @param mixed $data
     * @param mixed $id
     * @return mixed The created data
     */
    public static function create($data, $id = null);

    /**
     * @param string $node
     * @param mixed $id
     * @return array
     */
    public static function get($id = null);

    /**
     * Updates the given node at the given id with the given data
     * @param mixed $id
     * @param mixed $data
     * @return mixed The updated data
     */
    public static function update($id, $data);

    /**
     * Deletes data from a given node and id, if given.
     * @param mixed $id
     * @return null
     */
    public static function delete($id = null);

    /**
     * Sends the response out
     * @param mixed $response
     * @return void
     */
    public static function output($response);
}
