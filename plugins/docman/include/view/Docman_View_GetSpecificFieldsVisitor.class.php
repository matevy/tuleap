<?php
/**
 * Copyright (c) Enalean, 2014-Present. All Rights Reserved.
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
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

use Tuleap\Docman\Item\ItemVisitor;

class Docman_MetadataHtmlWiki extends Docman_MetadataHtml
{
    var $pagename;

    function __construct($pagename)
    {
        $this->pagename = $pagename;
    }

    public function getLabel($show_mandatory_information = true)
    {
        return $GLOBALS['Language']->getText('plugin_docman', 'specificfield_pagename');
    }

    function getField()
    {
        $hp = Codendi_HTMLPurifier::instance();
        return '<input type="text" class="docman_text_field" name="item[wiki_page]" value="'. $hp->purify($this->pagename) .'" /> ';
    }

    function &getValidator()
    {
        $msg = $GLOBALS['Language']->getText('plugin_docman', 'error_field_wiki_required');
        $validator = new Docman_ValidateValueNotEmpty($this->pagename, $msg);
        return $validator;
    }
}

class Docman_MetadataHtmlLink extends Docman_MetadataHtml
{
    var $link_url;

    function __construct($link_url)
    {
        $this->link_url = $link_url;
    }

    public function getLabel($show_mandatory_information = true)
    {
        return $GLOBALS['Language']->getText('plugin_docman', 'specificfield_url');
    }

    function getField()
    {
        $hp = Codendi_HTMLPurifier::instance();
        return '<input type="text" class="docman_text_field" name="item[link_url]" value="'. $hp->purify($this->link_url) .'" />';
    }

    function &getValidator()
    {
        $msg = $GLOBALS['Language']->getText('plugin_docman', 'error_field_link_required');
        $validator = new Docman_ValidateValueNotEmpty($this->link_url, $msg);
        return $validator;
    }
}

class Docman_MetadataHtmlFile extends Docman_MetadataHtml
{

    function __construct()
    {
    }

    public function getLabel($show_mandatory_information = true)
    {
        return $GLOBALS['Language']->getText('plugin_docman', 'specificfield_embeddedcontent');
    }

    function getField()
    {
        $html = '<input type="file" name="file" />';
        $html .= '<br /><em>'. $GLOBALS['Language']->getText(
            'plugin_docman',
            'max_size_msg',
            [formatByteToMb((int) ForgeConfig::get(PLUGIN_DOCMAN_MAX_FILE_SIZE_SETTING))]
        ) .'</em>';

        return $html;
    }

    public function &getValidator($request = null)
    {
        if ($request === null) {
            $request = HTTPRequest::instance();
        }
        $validator = new Docman_ValidateUpload($request);
        return $validator;
    }
}

class Docman_MetadataHtmlEmbeddedFile extends Docman_MetadataHtml
{
    var $content;
    function __construct($content)
    {
        $this->content = $content;
    }

    public function getLabel($show_mandatory_information = true)
    {
        return $GLOBALS['Language']->getText('plugin_docman', 'specificfield_embeddedcontent');
    }

    function getField()
    {
        $hp = Codendi_HTMLPurifier::instance();
        $html  = '';
        $html .= '<textarea id="embedded_content" name="content" cols="80" rows="20">'. $hp->purify($this->content) .'</textarea>';
        return $html;
    }

    function &getValidator()
    {
        $validator = null;
        return $validator;
    }
}

class Docman_MetadataHtmlEmpty extends Docman_MetadataHtml
{

    function __construct()
    {
    }

    public function getLabel($show_mandatory_information = true)
    {
        return $GLOBALS['Language']->getText('plugin_docman', 'specificfield_empty');
    }

    function getField()
    {
        return '';
    }

    function &getValidator()
    {
        $validator = null;
        return $validator;
    }
}

class Docman_View_GetSpecificFieldsVisitor implements ItemVisitor
{

    function visitFolder(Docman_Folder $item, $params = array())
    {
        return array();
    }
    function visitWiki(Docman_Wiki $item, $params = array())
    {
        $pagename = '';
        if (isset($params['force_item'])) {
            if (Docman_ItemFactory::getItemTypeForItem($params['force_item']) == PLUGIN_DOCMAN_ITEM_TYPE_WIKI) {
                $pagename = $params['force_item']->getPagename();
            }
        } else {
            $pagename = $item->getPagename();
        }
        return array(new Docman_MetadataHtmlWiki($pagename));
    }

    function visitLink(Docman_Link $item, $params = array())
    {
        $link_url = '';
        if (isset($params['force_item'])) {
            if ($params['force_item']->getType() == PLUGIN_DOCMAN_ITEM_TYPE_LINK) {
                $link_url = $params['force_item']->getUrl();
            }
        } else {
            $link_url = $item->getUrl();
        }
        return array(new Docman_MetadataHtmlLink($link_url));
    }

    function visitFile(Docman_File $item, $params = array())
    {
        return array(new Docman_MetadataHtmlFile($params['request']));
    }

    function visitEmbeddedFile(Docman_EmbeddedFile $item, $params = array())
    {
        $content = '';
        $version = $item->getCurrentVersion();
        if ($version) {
            $content = $version->getContent();
        }
        return array(new Docman_MetadataHtmlEmbeddedFile($content));
    }

    function visitEmpty(Docman_Empty $item, $params = array())
    {
        return array(new Docman_MetadataHtmlEmpty());
    }

    public function visitItem(Docman_Item $item, array $params = [])
    {
        throw new LogicException('Cannot get the specific fields of a non specialized item');
    }
}
