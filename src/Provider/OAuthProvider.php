<?php
namespace OCA\AuthserverLogin\Provider;

use OCA\AuthserverLogin\AppInfo\Application;
use OCP\IConfig;
use League\OAuth2\Client\Provider\GenericProvider;
use OCP\IURLGenerator;
use OCP\ISession;
use OC\User\LoginException;
use Hybridauth\Adapter\OAuth2;
use Hybridauth\Exception\Exception;

class OAuthProvider extends OAuth2
{

    /**
     *
     * @var IConfig
     */
    private $ncconfig;

    /**
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     *
     * @var ISession
     */
    private $session;

    public function __construct(IConfig $config, IURLGenerator $urlGenerator, ISession $session)
    {
        $this->ncconfig = $config;
        $this->urlGenerator = $urlGenerator;
        $this->session = $session;
        parent::__construct();
    }

    private function getConfig($key, $default = null)
    {
        return $this->ncconfig->getSystemValue('authserver_login_' . $key, $default);
    }

    protected function configure()
    {
        $this->clientId = $this->getConfig('client_id');
        $this->clientSecret = $this->getConfig('client_secret');
        $this->apiBaseUrl = $this->getConfig('base_url');
        $this->authorizeUrl = $this->getConfig('base_url') . '/oauth/v2/auth';
        $this->accessTokenUrl = $this->getConfig('base_url') . '/oauth/v2/token';
        $this->scope = $this->getConfig('scopes', 'profile:username profile:realname profile:email profile:groups');
        $this->setCallback($this->urlGenerator->linkToRouteAbsolute(Application::APPNAME . '.login.dologin'));
    }

    public function generateAuthorizeUrl()
    {
        return $this->getAuthorizeUrl();
    }

    public function generateLogoutUrl()
    {
        return $this->getConfig('base_url') . '/usr/kill-session';
    }

    public function getUserInformation(array $params)
    {
        try {
            if ($this->supportRequestState && $this->getStoredData('authorization_state') != $params['state']) {
                throw new LoginException('The authorization state [state=' . substr(htmlentities($params['state']), 0, 100) . '] ' . 'of this page is either invalid or has already been consumed.');
            }
            $response = $this->exchangeCodeForAccessToken($params['code']);

            $this->validateAccessTokenExchange($response);

            $this->initialize();
            return (array) $this->apiRequest('api/user.json');
        } catch (Exception $ex) {
            throw new LoginException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }
}