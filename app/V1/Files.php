<?php

namespace V1;

use Data\Json;
use const DATA;
use function uploadFiles;

/**
 * Description of Files
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Files extends Json {

    public static function post($data) {
        return uploadFiles($_FILES,
                           [
            'extensions' => ['jpg', 'png', 'gif'],
            'maxSize' => (1 * 1024 * 1000) / 2, // Half a mb
            'filename' => preg_replace('/[^a-zA-Z0-9]/', '-', $data['title']),
            'path' => DATA . $data['path']
        ]);
    }

}
