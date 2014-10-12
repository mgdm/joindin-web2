<?php
namespace User;

use Application\BaseController;
use Application\CacheService;

class UserController extends BaseController
{
    /**
     * Routes implemented by this class
     *
     * @param \Slim $app Slim application instance
     *
     * @return void
     */
    protected function defineRoutes(\Slim\Slim $app)
    {
        $app->get('/user/logout', array($this, 'logout'))->name('user-logout');
        $app->map('/user/login', array($this, 'login'))->via('GET', 'POST')->name('user-login');
        $app->map('/user/register', array($this, 'register'))->via('GET', 'POST')->name('user-register');
        $app->map('/user/password', array($this, 'password'))->via('GET', 'POST')->name('user-password');
    }

    /**
     * Login page
     *
     * @return void
     */
    public function login()
    {
        $config = $this->application->config('oauth');
        $request = $this->application->request();

        $error = false;
        if ($request->isPost()) {
            // handle submission of login form
        
            // make a call to the api with granttype=password
            $username = $request->post('username');
            $password = $request->post('password');
            $redirect = $request->post('redirect');
            $clientId = $config['client_id'];
            $clientSecret = $config['client_secret'];

            $authApi = new AuthApi($this->cfg, $this->accessToken);
            $result = $authApi->login($username, $password, $clientId, $clientSecret);

            if (false === $result) {
                $error = true;
            } else {
                session_regenerate_id(true);
                $_SESSION['access_token'] = $result->access_token;
                $this->accessToken = $_SESSION['access_token'];

                // now get users details
                $userApi = $this->getUserApi();
                $user = $userApi->getUser($result->user_uri);
                if ($user) {
                    $_SESSION['user'] = $user;
                    if (empty($redirect) || strpos($redirect, '/user/login') === 0) {
                        $this->application->redirect('/');
                    } else {
                        $this->application->redirect($redirect);
                    }
                } else {
                    unset($_SESSION['access_token']);
                }
            }
        }

        $this->render('User/login.html.twig', array('error' => $error));
    }

    /**
     * Registration page
     *
     * @return void
     */
    public function register()
    {
        // TODO: Implement!
        // This  method exists so that the named route can be defined
        // for the login page text.
        
        // For now, we just redirect to the legacy site
        header("Location: https://joind.in/user/register");
        exit;
    }

    /**
     * New password page.
     *
     * @return void.
     */
    public function password()
    {
        $request = $this->application->request();

        /** @var FormFactoryInterface $factory */ 
        $factory = $this->application->formFactory;
        $form    = $factory->create(new PasswordFormType());

        $error = false;
        if ($request->isPost()) { 
            $form->submit($request->post($form->getName()));
    
            if ($form->isValid()) {
                $data    = $form->getData();
                $userApi = $this->getUserApi();
                $result  = $userApi->setPassword($data['password']);

                if (false === $result) {
                    $error = true;     
                } else {
                    session_regenerate_id(true);
                    $_SESSION['access_token'] = $result->access_token;
                    $this->accessToken = $_SESSION['access_token'];

                    $this->render(
                        'User/passwordChanged.html.twig'
                    );

                    return;
                }
            }
        }

        $this->render(
            'User/password.html.twig',
            array(
                'form'  => $form->createView(),
                'error' => $error,
            )
        );
    }

    /**
     * Log out
     *
     * @return void
     */
    public function logout()
    {
        if (isset($_SESSION['user'])) {
            unset($_SESSION['user']);
        }
        if (isset($_SESSION['access_token'])) {
            unset($_SESSION['access_token']);
        }
        session_regenerate_id(true);
        $this->application->redirect('/');
    }

    /**
     *
     */
    protected function getUserApi() {
        $keyPrefix = $this->cfg['redisKeyPrefix'];
        $cache = new CacheService($keyPrefix);
        $userDb = new UserDb($cache);
        $userApi = new UserApi($this->cfg, $this->accessToken, $userDb);

        return $userApi;
    }
}
