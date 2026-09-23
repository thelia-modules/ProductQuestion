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

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * The module's entry in the back-office side navigation.
 *
 * A top-level entry, not a line inside a section: the default-twig side nav folds every
 * section's sub-entries behind a click, so an entry filed under Customers is invisible
 * until that section is opened. `main.in-top-menu-items` is the one point where a module
 * adds an item of its own to the list, after the built-in sections. Page, TheliaBlocks and
 * Option already sit there, at positions 1 to 3; declaring 4 puts this one right after
 * Option. The declared position is read once, when RegisterHookListenersPass creates the
 * module_hook row — after that the order belongs to the back office.
 *
 * Nothing declares this hook anywhere else. The pass reads getSubscribedHooks() while the
 * container is compiled, creates the row if it is missing, deletes a row whose method no
 * longer exists on this class, and registers the listener — so a change here only shows
 * once the container has been rebuilt with the module active.
 */
class BackHook extends BaseHook
{
    /**
     * @return array<string, list<array{type: string, method: string, position?: int}>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'main.in-top-menu-items' => [
                ['type' => 'back', 'method' => 'onMainInTopMenuItems', 'position' => 4],
            ],
        ];
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        // The theme passes the current route as admin_current_location, which is what lets
        // the entry light up on the module's own screens.
        $event->add($this->render('ProductQuestion/hook/menu-item.html.twig', $event->getTemplateVars()));
    }
}
