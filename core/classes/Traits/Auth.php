<?php

namespace Traits;

use Data\Json,
    Exception,
    Lcobucci\JWT\Builder,
    Lcobucci\JWT\Parser,
    Lcobucci\JWT\Signer\Hmac\Sha256,
    Lcobucci\JWT\ValidationData;
use function config;

/**
 * Description of Auth
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
trait Auth {

    /**
     * @var boolean|string False or ID of verified user
     */
    protected static $verified;

    /**
     * Verifies the token from the $_SERVER global variable
     * Requires that HTTP_BEARER and HTTP_CID are available
     * @return boolean|string False or ID of user
     * @throws Exception
     */
    public static function verify() {
        $jwt = filter_input(INPUT_SERVER, 'HTTP_BEARER');
        $aud = filter_input(INPUT_SERVER, 'HTTP_CID');
        static::$verified = false;
        if ($jwt && $aud) {
            $token = (new Parser())->parse($jwt);
            $data = new ValidationData;
            $data->setIssuer(config('app.url'));
            $data->setAudience($aud);
            // verify token came from this app
            if ($token->verify(new Sha256(), config('app.id'))) {
                $id = [
                    'aud' => $aud,
                    'token' => $jwt
                ];
                // get token from db
                $tokens = static::get($id);
                if (count($tokens)) {
                    // set id as audience for validation
                    $data->setId($tokens[0]['aud']);
                    // validate token and check exists in db
                    if ($token->validate($data)) {
                        static::$verified = $tokens[0]['user'];
                    }
                }
                // token is invalid. remove from db
                if (!static::$verified) static::delete($id);
            }
        }
        return static::$verified;
    }

    /**
     * Gets the expiration for the token
     * @return integer
     */
    private static function getExpirationTime() {
        return time() + 60 * config('app.session_timeout', 30); // 30 minutes
    }

    /**
     * Creates a new token from user data
     * @param array $user
     * @return array
     */
    public static function credentials(array $user) {
        $Builder = new Builder();
        $aud = (string) Json::createGUID();
        $iss = time();
        $exp = self::getExpirationTime();
        $Builder->setIssuer(config('app.url'))
                ->setAudience($aud)
                ->setId($aud, true)
                ->setIssuedAt($iss)
                ->setExpiration($exp)
                ->sign(new Sha256(), config('app.id'));
        $token = $Builder->getToken();
        $user_id = (string) $user['_id'];
        // delete all user token that are expired
        static::delete(['user' => $user_id, 'exp' => ['$lt' => time()]]);
        // save to db
        static::create([
            'aud' => $aud,
            'iss' => $iss,
            'exp' => $exp,
            'user' => $user_id,
            'token' => (string) $token
        ]);
        return [
            'token' => (string) $token,
            'cid' => $aud, // client id
            'user' => $user,
            'expires' => $exp
        ];
    }

    /**
     * Refreshes the token before it expires
     * @return string
     * @throws Exception
     */
    public static function refreshToken() {
        // verify that token is not expired
        if (!static::verify()) throw new Exception('Invalid token');
        $jwt = filter_input(INPUT_SERVER, 'HTTP_BEARER');
        $aud = filter_input(INPUT_SERVER, 'HTTP_CID');
        $id = [
            'aud' => $aud,
            'token' => $jwt
        ];
        // update db
        $Builder = new Builder();
        $iss = time();
        $exp = self::getExpirationTime();
        $Builder->setIssuer(config('app.url'))
                ->setAudience($aud)
                ->setId($aud, true)
                ->setIssuedAt($iss)
                ->setExpiration($exp)
                ->sign(new Sha256(), config('app.id'));
        $token = (string) $Builder->getToken();
        // update with new token details
        static::update($id,
                       [
            'iss' => $iss,
            'exp' => $exp,
            'token' => $token
        ]);
        // return token and expiration time
        return [
            'expires' => $exp,
            'token' => $token
        ];
    }

    public static function notImplemented() {
        throw new Exception('Not Implemented', 501);
    }

    protected static function searchableKeys() {
        return ['user' => 1, 'aud' => 1];
    }

    public static function verified() {
        return static::$verified;
    }

}
