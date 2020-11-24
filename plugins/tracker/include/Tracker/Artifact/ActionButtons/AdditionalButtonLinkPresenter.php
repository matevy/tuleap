<?php
/**
 * Copyright (c) Enalean, 2018 - Present. All Rights Reserved.
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

namespace Tuleap\Tracker\Artifact\ActionButtons;

class AdditionalButtonLinkPresenter
{
    /**
     * @var string
     */
    public $link_label;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $icon;

    /**
     * @var string
     */
    public $id;

    /**
     * @var array
     */
    public $data;

    public function __construct(string $link_label, string $url, ?string $icon = null, ?string $id = null, ?array $data = null)
    {
        $this->link_label = $link_label;
        $this->url        = $url;
        $this->icon       = $icon ? $icon : '';
        $this->id         = $id ? $id : '';
        $this->data       = $data ? $data : [];
    }
}
