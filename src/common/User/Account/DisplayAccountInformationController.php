<?php
/**
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace Tuleap\User\Account;

use CSRFSynchronizerToken;
use HTTPRequest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TemplateRenderer;
use TemplateRendererFactory;
use Tuleap\Layout\BaseLayout;
use Tuleap\Request\DispatchableWithBurningParrot;
use Tuleap\Request\DispatchableWithRequest;
use Tuleap\Request\ForbiddenException;

final class DisplayAccountInformationController implements DispatchableWithRequest, DispatchableWithBurningParrot
{
    /**
     * This is a temporary URL, the final one will be /account
     */
    public const URL = '/account/prefs';

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var CSRFSynchronizerToken
     */
    private $csrf_token;
    /**
     * @var TemplateRenderer
     */
    private $renderer;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        TemplateRendererFactory $renderer_factory,
        CSRFSynchronizerToken $csrf_token
    ) {
        $this->dispatcher = $dispatcher;
        $this->csrf_token = $csrf_token;
        $this->renderer   = $renderer_factory->getRenderer(__DIR__ . '/templates');
    }

    public static function buildSelf(): self
    {
        return new self(
            \EventManager::instance(),
            TemplateRendererFactory::build(),
            self::getCSRFToken(),
        );
    }

    public function process(HTTPRequest $request, BaseLayout $layout, array $variables)
    {
        $user = $request->getCurrentUser();
        if ($user->isAnonymous()) {
            throw new ForbiddenException();
        }

        $tabs = $this->dispatcher->dispatch(new AccountTabPresenterCollection($user, self::URL));
        assert($tabs instanceof AccountTabPresenterCollection);

        (new UserPreferencesHeader())->display(_('Account'), $layout);
        $this->renderer->renderToPage(
            'account-information',
            new AccountInformationPresenter(
                $tabs,
                $this->csrf_token,
                $user,
            )
        );
        $layout->footer([]);
    }

    private static function getCSRFToken(): CSRFSynchronizerToken
    {
        return new CSRFSynchronizerToken(self::URL);
    }
}
