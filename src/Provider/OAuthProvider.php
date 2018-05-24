<?php
namespace OCA\AuthserverLogin\Provider;

use OCA\AuthserverLogin\AppInfo\Application;
use OCP\IConfig;
use League\OAuth2\Client\Provider\GenericProvider;
use OCP\IURLGenerator;
use OCP\ISession;
use OC\User\LoginException;

class OAuthProvider
{

    /**
     *
     * @var IConfig
     */
    private $config;

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

    /**
     *
     * @var GenericProvider
     */
    private $provider;

    public function __construct(IConfig $config, IURLGenerator $urlGenerator, ISession $session)
    {
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->session = $session;
    }

    private function getConfig($key, $default = null)
    {
        return $this->config->getSystemValue('authserver_login_' . $key, $default);
    }

    /**
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
    private function getProvider()
    {
        if (!$this->provider) {
            $this->provider = new GenericProvider([
                'clientId' => $this->getConfig('client_id'),
                'clientSecret' => $this->getConfig('client_secret'),
                'redirectUri' => $this->urlGenerator->linkToRouteAbsolute(Application::APPNAME . '.login.dologin'),
                'urlAuthorize' => $this->getConfig('base_url') . '/oauth/v2/auth',
                'urlAccessToken' => $this->getConfig('base_url') . '/oauth/v2/token',
                'urlResourceOwnerDetails' => $this->getConfig('base_url') . '/api/user.json',
                'scopes' => $this->getConfig('scopes', 'profile:username profile:realname profile:email')
            ]);
        }
        return $this->provider;
    }

    public function generateAuthorizeUrl()
    {
        $authUrl = $this->getProvider()->getAuthorizationUrl();

        $this->session->set(Application::APPNAME . '.oauthstate', $this->getProvider()
            ->getState());

        return $authUrl;
    }

    public function getUserInformation(array $params)
    {
        try {
            if (!isset($params['state']) || $params['state'] !== $this->session->get(Application::APPNAME . '.oauthstate')) {
                throw new LoginException('Invalid state');
            }

            $accessToken = $this->getProvider()->getAccessToken('authorization_code', [
                'code' => $params['code']
            ]);

            return $this->getProvider()->getResourceOwner($accessToken);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $ex) {
            throw new LoginException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }
}