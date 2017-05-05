<?php

namespace Data;

use Exception;

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
     * Handle exceptions
     * @param Exception $ex
     * @return string Refined error message
     */
    public static function exceptions(Exception $ex);

    /**
     * Sends the response out
     * @param mixed $response
     * @return void
     */
    public static function output($response);
}
