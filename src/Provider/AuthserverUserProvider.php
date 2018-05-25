<?php
namespace OCA\AuthserverLogin\Provider;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IConfig;
use OC\User\LoginException;
use OCA\AuthserverLogin\Db\AuthserverLoginDAO;

class AuthserverUserProvider
{

    /**
     *
     * @var IUserManager
     */
    private $userManager;

    /**
     *
     * @var IGroupManager
     */
    private $groupManager;

    /**
     *
     * @var string
     */
    private $requiredGroup;

    /**
     *
     * @var string
     */
    private $groupPrefix;

    /**
     *
     * @var AuthserverLoginDAO
     */
    private $authserverLogin;

    public function __construct(IConfig $config, IUserManager $userManager, IGroupManager $groupManager, AuthserverLoginDAO $authserverLogin)
    {
        $this->requiredGroup = $config->getSystemValue('authserver_login_required_group');
        $this->groupPrefix = $config->getSystemValue('authserver_login_group_prefix');
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->authserverLogin = $authserverLogin;
    }

    public function getUser(array $userData)
    {
        if (isset($userData['error'])) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'Authserver returned error: ' . $userData['error'], 3);
            throw new LoginException('Authserver returned error: ' . $userData['error']);
        }

        if ($this->requiredGroup && !in_array($this->requiredGroup, $userData['groups'])) {
            \OCP\Util::writeLog('OC_USER_Authserver', 'User not in required group ' . $this->requiredGroup . ' (groups: ' . implode(', ', $userData['groups']) . ')', 3);
            throw new LoginException('User is not in required group');
        }

        $linkeduid = $this->authserverLogin->findUser($userData['guid']);

        if (!$linkeduid) {
            $username = isset($userData['username']) ? $userData['username'] : $userData['guid'];
            $password = substr(base64_encode(random_bytes(64)), 0, 30);
            $user = $this->userManager->createUser($username, $password);
            $this->authserverLogin->connect($user->getUID(), $userData['guid']);
        } else {
            $user = $this->userManager->get($linkeduid);
        }

        $displayname = isset($userData['name']) ? $userData['name'] : $username;
        $user->setDisplayName($displayname);

        if (isset($userData['primary-email']) && $user->getEMailAddress() !== $userData['primary-email'])
            $user->setEMailAddress($userData['primary-email']);

        if ($this->requiredGroup && isset($userData['groups'])) {
            $user->setEnabled(in_array($this->requiredGroup, $userData['groups']));
        } else {
            $user->setEnabled(false);
        }

        if ($this->groupPrefix && isset($userData['groups'])) {
            $authserverGroups = array_map(function ($groupName) {
                return substr($groupName, strlen($this->groupPrefix));
            }, array_filter($userData['groups'], function ($groupName) {
                return strpos($groupName, $this->groupPrefix) === 0;
            }));

            foreach ($authserverGroups as $groupName) {
                $owncloudGroup = $this->groupManager->createGroup($groupName);
                $owncloudGroup->addUser($user);
            }

            foreach ($this->groupManager->getUserGroups($user) as $owncloudGroup) {
                if (!in_array($owncloudGroup->getGID(), $authserverGroups))
                    $owncloudGroup->removeUser($user);
            }
        }

        return $user;
    }
}