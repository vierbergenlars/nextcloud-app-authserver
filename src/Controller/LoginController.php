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
use OCP\ISession;
use OC\User\LoginException;
use OCP\ILogger;

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

    /**
     *
     * @var ISession
     */
    private $session;

    /**
     *
     * @var ILogger
     */
    private $logger;

    public function __construct(IRequest $request, OAuthProvider $oauthProvider, AuthserverUserProvider $userProvider, IUserSession $userSession, IURLGenerator $urlGenerator, ISession $session, ILogger $logger)
    {
        parent::__construct(Application::APPNAME, $request);
        $this->oauthProvider = $oauthProvider;
        $this->userProvider = $userProvider;
        $this->userSession = $userSession;
        $this->urlGenerator = $urlGenerator;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @UseSession
     */
    public function dologin()
    {
        try {
            $userInformation = $this->oauthProvider->getUserInformation($this->request->getParams());
            $user = $this->userProvider->getUser($userInformation);

            $this->userSession->completeLogin($user, [
                'loginName' => $user->getUID(),
                'password' => null
            ], false);
            $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());

            $this->session->set('last-password-confirm', time());
        } catch (LoginException $e) {
            $this->logger->info('OAuth authentication failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            $this->session->set('loginMessages', [
                [],
                [
                    $e->getMessage()
                ]
            ]);
        }
        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }
}