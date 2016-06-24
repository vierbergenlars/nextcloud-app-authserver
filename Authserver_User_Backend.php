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

class Authserver_User_Backend extends Base implements IUserBackend {
    private $requiredGroup;
    private $groupPrefix;
    private $authserverUrl;

    public function __construct($authUrl, $requiredGroup, $groupPrefix = null) {
        $this->authserverUrl = $authUrl;
        $this->requiredGroup = $requiredGroup;
        $this->groupPrefix = $groupPrefix;
        parent::__construct($authUrl);
	}
	
	public function checkPassword( $uid, $password ) {
        $arr = explode('://', $this->authserverUrl, 2);
        if( ! isset($arr) OR count($arr) !== 2) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Invalid Url: "'.$this->authserverUrl.'" ', 3);
            return false;
        }
        list($protocol, $path) = $arr;
        $url= $protocol.'://'.urlencode($uid).':'.urlencode($password).'@'.$path;
        $data = file_get_contents($url);
        if($data===false) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Not possible to connect to Authserver Url: "'.$protocol.'://'.$path.'" ', 3);
            return false;
        }

        $decoded_data = @json_decode($data, true);

        if($decoded_data === null) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Cannot decode received JSON: '.json_last_error_msg(), 3);
            return false;
        }

        if(isset($decoded_data['error']))
            return false;

        if($decoded_data['username'] !== $uid)
            return false;

        if(!in_array($this->requiredGroup, $decoded_data['groups']) && !$this->userExists($decoded_data['username']))
            return false;

        $this->storeUser($decoded_data['username']);
        $this->setDisplayName($decoded_data['username'], $decoded_data['name']);

        $owncloudUser = \OC::$server->getUserManager()->get($decoded_data['username']);
        $owncloudUser->setEMailAddress($decoded_data['primary-email']);
        $owncloudUser->setEnabled(in_array($this->requiredGroup, $decoded_data['groups']));

        $authserverGroups = array_map(function($groupName)  {
            return substr($groupName, strlen($this->groupPrefix));
        }, array_filter($decoded_data['groups'], function($groupName) {
            return strpos($groupName, $this->groupPrefix) === 0;
        }));

        $groupManager = \OC::$server->getGroupManager();

        foreach($authserverGroups as $groupName) {
            $owncloudGroup = $groupManager->createGroup($groupName);
            $owncloudGroup->addUser($owncloudUser);
        }

        foreach($groupManager->getUserGroups($owncloudUser) as $owncloudGroup) {
            if(!in_array($owncloudGroup->getGID(), $authserverGroups))
                $owncloudGroup->removeUser($owncloudUser);
        }

        return $decoded_data['username'];
	}


    /**
     * Backend name to be shown in user management
     * @return string the name of the backend to be shown
     * @since 8.0.0
     */
    public function getBackendName()
    {
        return 'Authserver';
    }
}
