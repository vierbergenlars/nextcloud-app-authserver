<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Studentenraad campus Groep T
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace Studentenraad\Owncloud\AuthserverLogin;

use OCA\user_external\Base;
use OCP\IUserBackend;
use OCA\AuthserverLogin\Provider\AuthserverUserProvider;
use OCA\AuthserverLogin\Db\AuthserverLoginDAO;
use OC\User\LoginException;

class Authserver_User_Backend extends Base implements IUserBackend
{

    private $authserverUrl;

    public function __construct($authUrl)
    {
        $this->authserverUrl = $authUrl;
        parent::__construct($authUrl);
    }

    public function checkPassword($uid, $password)
    {
        $arr = explode('://', $this->authserverUrl, 2);
        if (!isset($arr) or count($arr) !== 2) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Invalid Url: "' . $this->authserverUrl . '" ', 3);
            return false;
        }
        list ($protocol, $path) = $arr;
        $url = $protocol . '://' . urlencode($uid) . ':' . urlencode($password) . '@' . $path;
        $data = file_get_contents($url);
        if ($data === false) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Not possible to connect to Authserver Url: "' . $protocol . '://' . $path . '" ', 3);
            return false;
        }

        $decoded_data = @json_decode($data, true);

        if ($decoded_data === null) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Cannot decode received JSON: ' . json_last_error_msg(), 3);
            return false;
        }

        $authserverLogin = \OC::$server->query(AuthserverLoginDAO::class);
        /* @var $authserverLogin AuthserverLoginDAO */

        if (isset($decoded_data['username'])) {
            $user = \OC::$server->getUserManager()->get($decoded_data['username']);
            if ($user && !$authserverLogin->findUser($decoded_data['guid'])) {
                $authserverLogin->connect($user->getUID(), $decoded_data['guid']);
            }
        }

        $userProvider = \OC::$server->query(AuthserverUserProvider::class);
        /* @var $userProvider AuthserverUserProvider */
        try {
            $user = $userProvider->getUser($decoded_data);
        } catch (LoginException $ex) {
            \OCP\Util::writeLog('OC_USER_Authserver', $ex->getMessage(), 3);
            return false;
        }

        if (!$user) {
            return false;
        }

        return $user->getUID();
    }

    /**
     * Backend name to be shown in user management
     *
     * @return string the name of the backend to be shown
     * @since 8.0.0
     */
    public function getBackendName()
    {
        return 'Authserver';
    }
}
