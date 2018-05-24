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

        // Util::addScript(static::APPNAME, 'style');
        $authorizeUrl = $provider->generateAuthorizeUrl();
        \OC_App::registerLogIn([
            'name' => $config->getSystemValue('authserver_login_label', 'Authserver'),
            'href' => $authorizeUrl
        ]);

        $useLoginRedirect = $config->getSystemValue('authserver_login_auto_redirect', false);
        if ($useLoginRedirect && $container->query(IRequest::class)->getPathInfo() === '/login') {
            header('Location: ' . $authorizeUrl);
            exit();
        }
    }
}