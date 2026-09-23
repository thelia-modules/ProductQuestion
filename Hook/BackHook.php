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

namespace ProductQuestion\Hook;

use ProductQuestion\ProductQuestion;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

/**
 * The module's entry in the back-office side navigation.
 *
 * The default-twig side nav has no free slot: a module reaches a section through the
 * hook_block() that section calls. Questions are filed under Customers, which is what the
 * entry is named after and where an administrator looks for what a customer sent.
 *
 * Nothing declares this hook anywhere else. RegisterHookListenersPass reads
 * getSubscribedHooks() when the container is compiled, creates the module_hook row if it is
 * missing, and registers the listener — so a new hook only appears once the container has
 * been rebuilt with the module active.
 */
class BackHook extends BaseHook
{
    /**
     * @return array<string, list<array{type: string, method: string}>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-customer' => [
                ['type' => 'back', 'method' => 'onMainTopMenuCustomer'],
            ],
        ];
    }

    public function onMainTopMenuCustomer(HookRenderBlockEvent $event): void
    {
        $event->add([
            'id' => 'customer_menu_product_question',
            'class' => '',
            'url' => URL::getInstance()->absoluteUrl(ProductQuestion::ADMIN_LIST_PATH),
            'title' => $this->trans('Customer questions', [], ProductQuestion::MESSAGE_DOMAIN),
        ]);
    }
}
