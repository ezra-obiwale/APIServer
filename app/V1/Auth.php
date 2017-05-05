<?php

namespace V1;

use Data\Mongo,
    Exception,
    Traits\Auth as TAuth;
use function _toCamel,
    config,
    createToken;

/**
 * Description of Auth
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Auth extends Mongo {

    use TAuth;

    /**
     * Redirects a request to the appropriate method
     * @param string $method
     * @param array $params
     * @param array $allowedMethods
     * @return mixed
     * @throws Exception
     */
    private static function callMethod($method, array $params,
                                       array $allowedMethods = []) {
        $method = lcfirst(_toCamel(str_replace('-', '_', $method)));
        if ((count($allowedMethods) && !in_array($method, $allowedMethods))
                || !method_exists(self::class, $method)) {
            throw new Exception('Not Implemented', 501);
        }
        return call_user_func_array([self, $method], $params);
    }

    /**
     * Handles GET request 
     * @param string $id
     * @return mixed
     * @throws Exception
     */
    public static function read($id) {
        if (is_string($id)) {
            $parts = explode('/', $id);
            $action = array_shift($parts);
            return self::callMethod($action, $parts, ['verifyEmail', 'logout']);
        }
        throw new Exception('Not Implemented', 501);
    }

    /**
     * Handles POST requests
     * @param array $data
     * @param string $action
     * @return mixed
     */
    public static function post($data, $action) {
        return self::callMethod($action, [$data],
                                [
                    'signup', 'login', 'startResetPassword', 'resetPassword', 'resendVerificationEmail'
        ]);
    }

    /**
     * Handles PUT requests
     * @param string $action
     * @param array $data
     * @return mixed
     */
    public static function put($action, array $data = []) {
        return self::callMethod($action, [$data], ['refreshToken']);
    }

    /**
     * @param array $data
     * @return boolean|array
     */
    public static function signup(array $data) {
        $token = createToken();
        $data['email_token'] = $token;
        if ($user = User::create($data)) {
            unset($user['email_token']);
            unset($user['reset_token']);
            return $user;
        }
        throw new Exception();
    }

    /**
     * Verifies a new user's email address
     * @param string $token
     * @return void
     */
    public static function verifyEmail($token) {
        $users = User::get(['email_token' => $token], false);
        if (count($users)) {
            $user = (array) $users[0];
            if ($user['verified']) {
                header('Location: ' . config('app.urls.client.email_verification_retried'));
                exit;
            }
            $user['verified'] = time();
            if (User::update(User::getId($user), $user, ['replace' => true])) {
                header('Location: ' . config('app.urls.client.email_verification_success'));
                exit;
            }
        }
        header('Location: ' . config('app.urls.client.email_verification_failure'));
        exit;
    }

    /**
     * Resends verification email back to the user
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public static function resendVerificationEmail($data) {
        self::validate($data,
                       [
            'email' => 'required|email'
        ]);
        $user = User::getOneByEmail($data['email']);
        if (!$user['email_token'])
                throw new Exception('Email has already been verified');
        return self::sendVerificationEmail($user);
    }

    /**
     * @param array $data
     * @return array
     */
    public static function login(array $data) {
        self::validate($data,
                       [
            'email' => 'required',
            'password' => 'required'
        ]);
        $user = User::getOneByEmail($data['email'], false);
        // or password isn't correct
        if (!password_verify($data['password'], $user['password']))
                throw new Exception('Invalid credentials');
        // email not verified yet
        if ($user['email_token'])
                throw new Exception('Email not verified yet. Check you inbox.');
        // remove sensitive information
        unset($user['password']);
        unset($user['email_token']);
        unset($user['reset_token']);
        return self::credentials($user);
    }

    /**
     * Deletes the token from the database. Even though the token is still valid,
     * it won't work anymore since the db is checked when verifying.
     * @return mixed
     * @throws Exception
     */
    public static function logout() {
        // verify that token is not expired
        if (!self::verify()) throw new Exception('Invalid token');
        return self::delete([
                    'token' => filter_input(INPUT_SERVER, 'HTTP_BEARER'),
                    'aud' => filter_input(INPUT_SERVER, 'HTTP_CID'),
        ]);
    }

    /**
     * Initiates a reset password and send an email to the user
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public static function startResetPassword(array $data) {
        $user = User::getOneByEmail($data['email']);
        $token = createToken();
        User::update(User::getId($user), ['reset_token' => $token],
                                 ['replace' => false]);
        $resetLink = config('app.urls.client.password_reset');
        if (!strstr($resetLink, '?')) $resetLink .= '?';
        else $resetLink .= '&';
        User::sendPasswordResetEmail($user, $resetLink . 'token=' . $token);
    }

    /**
     * Resets a user's password
     * @param array $data
     * @return boolean|array Array of user details
     * @throws Exception
     */
    public static function resetPassword(array $data) {
        self::validate($data,
                       [
            'token' => 'required',
                ], [
            'token' => 'Invalid token',
        ]);
        $users = User::get(['reset_token' => $data['token']], false);
        if (!count($users)) throw new Exception('Invalid token');
        $user = (array) $users[0];
        unset($user['reset_token']);
        $user['password'] = $data['password'];
        $user['confirm'] = $data['confirm'];
        if ($user = User::update(User::getId($user), $user)) {
            unset($user['password']);
            return $user;
        }
        throw new Exception();
    }

}
