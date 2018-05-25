<?php
namespace OCA\AuthserverLogin\AppInfo;

use OCP\IConfig;
use OCP\IContainer;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\AppFramework\App;
use OCA\AuthserverLogin\Db\AuthserverLoginDAO;
use OCA\AuthserverLogin\Provider\AuthserverUserProvider;
use OCA\AuthserverLogin\Provider\OAuthProvider;
use OCP\IUserManager;
use OCP\IUserSession;

class Application extends App
{

    const APPNAME = 'authserver-login';

    public function __construct()
    {
        parent::__construct(self::APPNAME);
    }

    public function register()
    {
        $container = $this->getContainer();

        $container->registerService(AuthserverLoginDAO::class, function (IContainer $c) {
            return new AuthserverLoginDAO($c->query(\OCP\IDBConnection::class));
        });

        $container->registerService(OAuthProvider::class, function (IContainer $c) {
            return new OAuthProvider($c->query(IConfig::class), $c->query(IURLGenerator::class), $c->query(ISession::class));
        });

        $container->registerService(AuthserverUserProvider::class, function (IContainer $c) {
            return new AuthserverUserProvider($c->query(IConfig::class), $c->query(\OCP\IUserManager::class), $c->query(\OCP\IGroupManager::class), $c->query(AuthserverLoginDAO::class));
        });

        $config = $container->query(IConfig::class);
        /* @var $config IConfig */

        $provider = $container->query(OAuthProvider::class);
        /* @var $provider OAuthProvider */

        $session = $container->query(ISession::class);
        /* @var $session ISession */

        $isLogin = $container->query(IRequest::class)->getPathInfo() === '/login';

        if ($isLogin) {
            // Util::addScript(static::APPNAME, 'style');
            $authorizeUrl = $provider->generateAuthorizeUrl();
            \OC_App::registerLogIn([
                'name' => $config->getSystemValue('authserver_login_label', 'Authserver'),
                'href' => $authorizeUrl
            ]);
        }

        $useLoginRedirect = $config->getSystemValue('authserver_login_auto_redirect', false) && !$session->exists('loginMessages');
        if ($useLoginRedirect && $isLogin) {
            header('Location: ' . $authorizeUrl);
            exit();
        }

        $this->registerHooks();
    }

    public function registerHooks()
    {
        $container = $this->getContainer();
        $session = $container->query(IUserSession::class);
        /* @var $session IUserSession */
        $session->listen('\OC\User', 'preDelete', function ($user) use ($container) {
            /* @var $user \OC\User\User */
            $loginDao = $container->query(AuthserverLoginDAO::class);
            /* @var $loginDao AuthserverLoginDAO */
            $loginDao->disconnect($user->getUid());
        });

        $session->listen('\OC\User', 'postLogout', function () use ($container) {
            $provider = $container->query(OAuthProvider::class);
            /* @var $provider OAuthProvider */
            header('Location: ' . $provider->generateLogoutUrl());
            exit();
        });
    }
}