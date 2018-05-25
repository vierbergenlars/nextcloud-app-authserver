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

    private $enableOauthCache = null;

    public function __construct()
    {
        parent::__construct(self::APPNAME);
    }

    private function enableOAuth()
    {
        if ($this->enableOauthCache === null) {
            $config = $this->getContainer()->query(IConfig::class);
            /* @var $config IConfig */
            $this->enableOauthCache = !!$config->getSystemValue('authserver_login_client_id');
        }
        return $this->enableOauthCache;
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

        if ($this->enableOAuth()) {
            $isLogin = $container->query(IRequest::class)->getPathInfo() === '/login';
            if ($isLogin) {
                $config = $container->query(IConfig::class);
                /* @var $config IConfig */
                $provider = $container->query(OAuthProvider::class);
                /* @var $provider OAuthProvider */
                $authorizeUrl = $provider->generateAuthorizeUrl();
                \OC_App::registerLogIn([
                    'name' => $config->getSystemValue('authserver_login_label', 'Authserver'),
                    'href' => $authorizeUrl
                ]);
                $session = $container->query(ISession::class);
                /* @var $session ISession */
                $useLoginRedirect = $config->getSystemValue('authserver_login_auto_redirect', false) && !$session->exists('loginMessages');
                if ($useLoginRedirect) {
                    header('Location: ' . $authorizeUrl);
                    exit();
                }
            }
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

        if ($this->enableOAuth()) {

            $session->listen('\OC\User', 'postLogout', function () use ($container) {
                $provider = $container->query(OAuthProvider::class);
                /* @var $provider OAuthProvider */
                header('Location: ' . $provider->generateLogoutUrl());
                exit();
            });
        }
    }
}