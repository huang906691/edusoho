<?php

namespace Topxia\MobileBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;
use Topxia\WebBundle\Form\RegisterType;
use Topxia\Common\SimpleValidator;

class UserController extends MobileController
{

    public function __construct()
    {
        $this->setResultStatus();
    }

    public function getUserAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        return $this->createJson($request, $user);
    }

    public function checkTokenAction(Request $request)
    {
        $token = $this->getUserToken($request);
        $result = array(
            "token"=>$token["token"]
        );
        if ($token) {
            $user = $this->getUserService()->getUser($token["userId"]);
            $result["user"] = $this->changeUserPicture($user,false);
        }
        
        return $this->createJson($request, $result);
    }

    public function getNoticeAction(Request $request)
    {
        $token = $this->getUserToken($request);
        if ($token) {
            $user = $this->getCurrentUser();
            if (!$user) {
                throw $this->createAccessDeniedException();
            }
            $page = $this->getParam($request, 'page', 0);
            $count = $this->getNotificationService()->getUserNotificationCount($token['userId']);
            $notifications = $this->getNotificationService()->findUserNotifications(
                $token['userId'],
                $page,
                MobileController::$defLimit
            );

            $notifications = $this->changeCreatedTime($notifications);
            $this->setResultStatus("success");
            $this->result['notifications'] = $notifications;
            $this->result = $this->setPage($this->result, $page, $count);
            $this->getNotificationService()->clearUserNewNotificationCounter($token['userId']);
        }
        return $this->createJson($request, $this->result);
    }

    public function registUserAction(Request $request)
    {
        $registration = array(
            "email"=>$request->query->get('email'),
            "password"=>$request->query->get('password'),
            "nickname"=>$request->query->get('nickname'),
        );

        $vaildResult = $this->vaildRegistration($registration);
        if (empty($vaildResult)) {
            $registration['createdIp'] = $request->getClientIp();
            if (!$this->getUserService()->isNicknameAvaliable($registration['nickname'])) {
                $this->result['message'] = "昵称已存在";
            }
            if (!$this->getUserService()->isEmailAvaliable($registration['email'])) {
                $this->result['message'] = "邮箱已注册";
            }
            if (!isset($result['message'])) {
                $user = $this->getAuthService()->register($registration);
                $this->authenticateUser($user);
                $this->sendRegisterMessage($user);
                $this->result['token'] = $token = $this->createToken($user, $request);
                $this->setResultStatus("success");
            }
        } else {
            $result['message'] = $vaildResult['message'];
        }
        
        return $this->createJson($request, $this->result);
    }

    public function checkLoginAction(Request $request)
    {
    	$email = $request->query->get('email');
    	$pass = $request->request->get('pass');
    	$user = $this->getUserService()->getUserByEmail($email);

    	if ($user) {
    		$this->setResultStatus("success");
    	}
    	return $this->createJson($request, $this->result);
    }

    public function userLoginAction(Request $request)
    {
        $username = $request->query->get('_username');
        $user = $this->loadUserByUsername($request, $username);
        if ($user) {
            $pass = $request->query->get('_password');
            if ($this->getUserService()->verifyPassword($user['id'], $pass)) {
                $token = $this->createToken($user, $request);
                $this->result['token'] = $token;
                $this->result['user'] = $this->changeUserPicture($user, false);
                $this->setResultStatus("success");
            }
        }
        return $this->createJson($request, $this->result);
    }

    public function logoutAction(Request $request)
    {
        $token = $request->query->get('token');
        if ($this->getUserService()->deleteToken(UserController::$mobileType, $token)) {
            $this->setResultStatus("success");
        }
        return $this->createJson($request, $this->result);
    }

    private function loadUserByUsername ($request, $username) {
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user = $this->getUserService()->getUserByEmail($username);
        } else {
            $user = $this->getUserService()->getUserByNickname($username);
        }

        if (empty($user)) {
            return null;
        }
        $user['currentIp'] = $request->getClientIp();

        return $user;
    }

    protected function vaildRegistration($registration)
    {
        $msg = null;
        $result = null;
        if (!SimpleValidator::email($registration['email'])) {
            $msg= "邮箱格式不正确";
        }else if (!SimpleValidator::nickname($registration['nickname'])) {
            $msg = "昵称格式不正确";
        } else if (!SimpleValidator::password($registration['password'])) {
            $msg = "密码格式不正确";
        }
        if ($msg) {
            $result = array("message"=>$msg);
        }
        return $result;
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }
}
