<?php

namespace V1;

use Data\Mongo;

/**
 * Description of User
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class User extends Mongo {

    protected static function uniqueKeys() {
        return ['name' => 1, 'email' => 1];
    }

    public static function get($id = null, $projection = true) {
        if ($projection === true)
                $projection = ['email_token' => 0, 'reset_token' => 0, 'password' => 0];
        return parent::get($id, $projection);
    }

    public static function create($data, $id = null) {
        $user = parent::create($data, $id);
        self::sendVerificationEmail($user);
        unset($user['password']);
        return $user;
    }

    public static function getOneByEmail($email, $removeSensitiveInfos = true) {
        $users = self::get(['email' => $email], false);
        if (!count($users)) throw new Exception('Invalid email');
        $user = (array) $users[0];
        if ($removeSensitiveInfos) {
            unset($user['password']);
        }
        return $user;
    }

    protected static function preSave(&$data, $id, array $options = array()) {
        if (!isset($options['replace']) || $options['replace']) {
            $rules = [
                'name' => 'required|min:6',
                'email' => 'required|email',
            ];
            if (!$id) {
                // creating new user
                $rules['password'] = 'required|min:8';
                $rules['confirm'] = 'match:password';
            }
            static::validate($data, $rules,
                             [
                'name' => 'Company name must be at least 6 chars',
                'confirm' => 'Passwords mismatch'
            ]);
            if ($data['confirm'])
                    $data['password'] = password_hash($data['password'],
                                                      PASSWORD_DEFAULT);
            unset($data['confirm']);
        }
    }

    /**
     * 
     * @param array $user
     * @return boolean
     */
    public static function sendVerificationEmail(array $user) {
        return email('email-verification',
                     [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'VERIFICATION_LINK' => aLink(url(self::getNamespace()
                                    . '/auth/verify-email/' . $user['email_token']))
                ])->send($user['email'],
                         'Verify your email for ' . config('app.name'));
    }

    public static function sendPasswordResetEmail(array $user, $resetLink) {
        return email('password-reset',
                     [
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'RESET_LINK' => aLink($resetLink)
                        ])
                        ->send($user['email'],
                               'Reset your password for ' . config('app.name'));
    }

    public static function getId(array $user) {
        return (string) $user['_id'];
    }

}
