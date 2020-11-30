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
 */

declare(strict_types=1);

namespace Tuleap\OAuth2Server\Administration;

/**
 * @psalm-immutable
 */
final class AppPresenter
{
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $redirect_endpoint;
    /** @var string */
    public $client_id;
    /** @var bool */
    public $use_pkce;

    public function __construct(int $id, string $name, string $redirect_endpoint, string $client_id, bool $use_pkce)
    {
        $this->id                = $id;
        $this->name              = $name;
        $this->redirect_endpoint = $redirect_endpoint;
        $this->client_id         = $client_id;
        $this->use_pkce          = $use_pkce;
    }
}
