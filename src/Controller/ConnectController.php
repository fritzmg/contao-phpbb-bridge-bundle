<?php
/*
 * This file is part of contao-phpbbBridge
 * 
 * Copyright (c) CTS GmbH
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 */

namespace Ctsmedia\Phpbb\BridgeBundle\Controller;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Environment;
use Contao\FrontendIndex;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use Ctsmedia\Phpbb\BridgeBundle\PageType\Forum;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 *
 * @package Ctsmedia\Phpbb\BridgeBundle\Controller
 * @author Daniel Schwiperich <d.schwiperich@cts-media.eu>
 *
 * @Route("/phpbb_bridge", defaults={"_scope" = "frontend"})
 */
class ConnectController extends Controller
{
    /**
     * @var FrontendIndex null
     */
    protected $frontendIndex = null;


    /**
     * Call this function to validate the incoming request
     * @todo move the validation in symfony security firewall authentication provider or request_matcher ?
     */
    protected function validateRequest()
    {
        // Initialize Contao
        $this->container->get('contao.framework')->initialize(); // we need to do this for autoloading contao classes
        $req = $this->container->get('request');
        /* @var $req Request */

        // Only requests from the bridge itself are allowed. Check if the specific header is set
        if ($req->headers->get('x-requested-with') != 'ContaoPhpbbBridge') {
            System::log('Not allowed to access phpbb bridge. Seems not coming fron the bridge', __METHOD__, TL_ERROR);
            throw new AccessDeniedException('Not allowed to access phpbb bridge');
        }
        // The bridge also always sets a internal proxy header
        if (!$req->headers->get('x-forwarded-for')) {
            System::log('Not allowed to access phpbb bridge without proxy header', __METHOD__, TL_ERROR);
            throw new AccessDeniedException('Not allowed to access phpbb bridge without proxy header');
        }

        // Make sure we have an internat request
        // we cannot use $req->server->get('REMOTE_ADDR') here, because symfone alters it
        if($_SERVER['REMOTE_ADDR'] != Environment::get('server') ){
            System::log('IPs did not match. clientIP: '.
                $req->getClientIp().'| EnvClientIp '.Environment::get('ip').'| EnvServerIp '.Environment::get('server'),
            __METHOD__, TL_ERROR);
            throw new AccessDeniedException('Not allowed to access phpbb bridge without proxy header');
        }

        $req->attributes->set('isInternalForumRequest', true);
        $this->frontendIndex = new FrontendIndex();

    }

    /**
     *
     * @Route("/test")
     */
    public function testAction()
    {
        $this->validateRequest();

        return new Response();

//        $content = dump(Config::get('disableIpCheck'));
//
//        $response = new Response();
//        $response->setContent($content);
//
//        return $response;
    }

    /**
     * Login a user to contao via incoming phpbb POST login request
     *
     * @Route("/login")
     *
     * @todo implement security to avoid brute force etc
     * @todo implement authentication to access API (token, digest, basic...). Maybe not needed since we recheck credentials during login process against phpbb
     *
     */
    public function loginAction()
    {
        $this->validateRequest();
        //dump(Config::get('disableIpCheck'));

        $user = FrontendUser::getInstance();
        $result = $user->login();

        $response = new JsonResponse();
        $response->setData(array(
            'login_status' => $result
        ));

        return $response;
    }

    /**
     * Logout a user from contao via incoming phpbb logout request
     *
     * @Route("/logout")
     */
    public function logoutAction()
    {
        $this->validateRequest();
        $user = FrontendUser::getInstance();
        $result = $user->logout();

        $response = new JsonResponse();
        $response->setData(array(
            'logout_status' => $result
        ));

        return $response;
    }

    /**
     *
     * @Route("/layout")
     */
    public function layoutAction()
    {
        $this->validateRequest();

        $objPage = PageModel::findOneByType('phpbb_forum');
        Environment::set('relativeRequest', $objPage->alias);
        $response = $this->frontendIndex->run();
        if ($objPage instanceof PageModel) {
            $page = new Forum();
            Input::setGet('format', 'json');
            $response = $page->getResponse($objPage);
        }
        return $response;
    }

}