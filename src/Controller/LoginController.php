<?php
namespace OCA\AuthserverLogin\Controller;

use OCA\AuthserverLogin\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCA\AuthserverLogin\Provider\OAuthProvider;
use OCA\AuthserverLogin\Db\AuthserverLoginDAO;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\AuthserverLogin\Provider\AuthserverUserProvider;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\AppFramework\Http\RedirectResponse;

class LoginController extends Controller
{

    /**
     *
     * @var OAuthProvider
     */
    private $oauthProvider;

    /**
     *
     * @var AuthserverUserProvider
     */
    private $userProvider;

    /**
     *
     * @var IUserSession
     */
    private $userSession;

    /**
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    public function __construct(IRequest $request, OAuthProvider $oauthProvider, AuthserverUserProvider $userProvider, IUserSession $userSession, IURLGenerator $urlGenerator)
    {
        parent::__construct(Application::APPNAME, $request);
        $this->oauthProvider = $oauthProvider;
        $this->userProvider = $userProvider;
        $this->userSession = $userSession;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     */
    public function dologin()
    {
        $userInformation = $this->oauthProvider->getUserInformation($this->request->getParams());
        $user = $this->userProvider->getUser($userInformation);

        $this->userSession->setUser($user);

        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }
}