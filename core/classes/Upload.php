<?php

use Exception;

/**
 * Description of Upload
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Upload extends JsonData {

    public static function create($data, $id = null) {
        return uploadFiles($_FILES, [
            'extensions' => ['jpg', 'png', 'gif'],
            'maxSize' => (1 * 1024 * 1000) / 2, // Half a mb
            'filename' => preg_replace('/[^a-zA-Z0-9]/', '-', $data['title']),
            'path' => DATA . $data['path']
        ]);
    }

    public static function get($id = null) {
        throw new Exception('Not implemented');
    }

    public static function update($id, $data) {
        throw new Exception('Not implemented');
    }

    public static function delete($path = null) {
        throw new Exception('Not implemented');
    }

}
