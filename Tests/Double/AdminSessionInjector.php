<?php

declare(strict_types=1);

/*
 * This file is part of the ProductQuestion module for Thelia 3.
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductQuestion\Tests\Double;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\Admin;

/**
 * Signs an administrator in on every request of a web test, between the session set-up and
 * the admin firewall: what a real back-office session holds, without the login form.
 */
final class AdminSessionInjector implements EventSubscriberInterface
{
    private ?Admin $admin = null;

    public function setAdmin(?Admin $admin): void
    {
        $this->admin = $admin;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (null === $this->admin || !$request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set('thelia.admin_user', $this->admin);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 200]];
    }
}
