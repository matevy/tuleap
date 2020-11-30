<?php
/**
 * Copyright (c) Enalean, 2015 - present. All Rights Reserved.
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

use Tuleap\Tracker\Artifact\Artifact;

class Tracker_Artifact_Changeset_CommentTest extends \PHPUnit\Framework\TestCase  // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace,Squiz.Classes.ValidClassName.NotCamelCaps
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use \Tuleap\GlobalLanguageMock;

    /**
     * @var string
     */
    private $timestamp;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|Tracker_Artifact_Changeset
     */
    private $changeset;
    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|UserManager
     */
    private $user_manager;

    protected function setUp(): void
    {
        parent::setUp();

        $user            = Mockery::mock(PFUser::class);
        $user->shouldReceive('getId')->andReturn(101);
        $user->shouldReceive('getLdapId')->andReturn("ldap_01");
        $this->changeset = Mockery::mock(Tracker_Artifact_Changeset::class);
        $this->timestamp = '1433863107';

        $this->user_manager = Mockery::mock(\UserManager::class);
        $this->user_manager->shouldReceive('getUserById')->withArgs([101])->andReturn($user);
    }

    public function testItExportsToXML(): void
    {
        $body = '<b> My comment 01</b>';

        $comment = new Tracker_Artifact_Changeset_Comment(
            1,
            $this->changeset,
            0,
            0,
            101,
            $this->timestamp,
            $body,
            'html',
            0
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <comments/>';

        $changeset_node    = new SimpleXMLElement($xml);
        $user_xml_exporter = new UserXMLExporter($this->user_manager, \Mockery::spy(\UserXMLExportedCollection::class));

        $comment->exportToXML($changeset_node, $user_xml_exporter);

        $this->assertNotNull($changeset_node->comment);
        $this->assertNotNull($changeset_node->comment->submitted_by);
        $this->assertNotNull($changeset_node->comment->submitted_on);
        $this->assertNotNull($changeset_node->comment->body);

        $this->assertEquals((string) $changeset_node->comment->submitted_by, 'ldap_01');
        $this->assertEquals($changeset_node->comment->submitted_by['format'], 'ldap');

        $this->assertEquals((string) $changeset_node->comment->submitted_on, '2015-06-09T17:18:27+02:00');
        $this->assertEquals($changeset_node->comment->submitted_on['format'], 'ISO8601');

        $this->assertEquals((string) $changeset_node->comment->body, $body);
        $this->assertEquals($changeset_node->comment->body['format'], 'html');
    }

    public function testItExportsToXMLWithCrossReferencesEscaped(): void
    {
        $body         = 'See art #290';
        $escaped_body = 'See art # 290';

        $comment = new Tracker_Artifact_Changeset_Comment(
            1,
            $this->changeset,
            0,
            0,
            101,
            $this->timestamp,
            $body,
            'html',
            0
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <comments/>';

        $changeset_node    = new SimpleXMLElement($xml);
        $user_xml_exporter = new UserXMLExporter($this->user_manager, \Mockery::spy(\UserXMLExportedCollection::class));

        $comment->exportToXML($changeset_node, $user_xml_exporter);

        $this->assertNotNull($changeset_node->comment);
        $this->assertNotNull($changeset_node->comment->submitted_by);
        $this->assertNotNull($changeset_node->comment->submitted_on);
        $this->assertNotNull($changeset_node->comment->body);

        $this->assertEquals((string) $changeset_node->comment->submitted_by, 'ldap_01');
        $this->assertEquals($changeset_node->comment->submitted_by['format'], 'ldap');

        $this->assertEquals((string) $changeset_node->comment->submitted_on, '2015-06-09T17:18:27+02:00');
        $this->assertEquals($changeset_node->comment->submitted_on['format'], 'ISO8601');

        $this->assertEquals((string) $changeset_node->comment->body, $escaped_body);
        $this->assertEquals($changeset_node->comment->body['format'], 'html');
    }

    public function testCheckCommentFormat(): void
    {
        $this->assertEquals('text', Tracker_Artifact_Changeset_Comment::checkCommentFormat('text'));
        $this->assertEquals('html', Tracker_Artifact_Changeset_Comment::checkCommentFormat('html'));
        $this->assertEquals('text', Tracker_Artifact_Changeset_Comment::checkCommentFormat('not_valid'));
        $this->assertEquals('text', Tracker_Artifact_Changeset_Comment::checkCommentFormat(true));
    }

    public function testItReturnsEmptyWhenFormatIsTextAndWhenItHasEmptyBody(): void
    {
        $comment = Mockery::mock(
            Tracker_Artifact_Changeset_Comment::class,
            [
                1,
                $this->changeset,
                0,
                0,
                101,
                $this->timestamp,
                "",
                'text',
                0
            ]
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $this->assertEquals("", $comment->fetchMailFollowUp('text'));
    }

    public function testItClearsComment(): void
    {
        $comment = Mockery::mock(
            Tracker_Artifact_Changeset_Comment::class,
            [
                1,
                $this->changeset,
                0,
                0,
                101,
                $this->timestamp,
                " ",
                'html',
                1234
            ]
        )->makePartial()->shouldAllowMockingProtectedMethods();
        $user = $this->getAMockedUser();
        $comment->shouldReceive('getCurrentUser')->andReturn($user);

        $this->assertStringContainsString("Comment has been cleared", $comment->fetchMailFollowUp());
    }

    public function testItDisplayStandardComment(): void
    {
        $tracker = Mockery::mock(Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturn(101);
        $artifact                      = Mockery::mock(Artifact::class);
        $artifact->shouldReceive('getTracker')->andReturn($tracker);
        $this->getPurifier($artifact);

        $body    = 'See art #290';
        $comment = Mockery::mock(
            Tracker_Artifact_Changeset_Comment::class,
            [
                1,
                $this->changeset,
                0,
                0,
                101,
                $this->timestamp,
                $body,
                'html',
                0
            ]
        )->makePartial()->shouldAllowMockingProtectedMethods();
        $user = $this->getAMockedUser();
        $comment->shouldReceive('getCurrentUser')->andReturn($user);
        $purifier = Mockery::mock(Codendi_HTMLPurifier::class);
        $purifier->shouldReceive('purifyHTMLWithReferences')->andReturn($body);
        $comment->shouldReceive('getPurifier')->andReturn($purifier);

        $follow_up = $comment->fetchMailFollowUp();
        $this->assertStringNotContainsString("Comment has been cleared", $follow_up);
        $this->assertStringNotContainsString("Updated comment", $follow_up);
    }

    public function testItDisplayEditedComment(): void
    {
        $tracker = Mockery::mock(Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturn(101);
        $artifact                      = Mockery::mock(Artifact::class);
        $artifact->shouldReceive('getTracker')->andReturn($tracker);

        $this->changeset->artifact = $artifact;
        $body                      = 'See art #290';
        $comment                   = Mockery::mock(
            Tracker_Artifact_Changeset_Comment::class,
            [
                1,
                $this->changeset,
                0,
                0,
                101,
                $this->timestamp,
                $body,
                'html',
                1234
            ]
        )->makePartial()->shouldAllowMockingProtectedMethods();
        $user = $this->getAMockedUser();
        $comment->shouldReceive('getCurrentUser')->andReturn($user);
        $purifier = Mockery::mock(Codendi_HTMLPurifier::class);
        $purifier->shouldReceive('purifyHTMLWithReferences')->andReturn($body);
        $comment->shouldReceive('getPurifier')->andReturn($purifier);

        $this->assertStringContainsString("Updated comment", $comment->fetchMailFollowUp());
    }

    private function getAMockedUser()
    {
        $user = Mockery::mock(PFUser::class);

        $user->shouldReceive('fetchHtmlAvatar')->once();
        $user->shouldReceive('getTimezone');
        $user->shouldReceive('getId')->once();
        $user->shouldReceive('getEmail')->once();
        $user->shouldReceive('getRealName')->once();
        $user->shouldReceive('getUserName')->once();
        $user->shouldReceive('isAnonymous')->once()->andReturnFalse();
        return $user;
    }

    /**
     * @param $artifact
     */
    private function getPurifier($artifact): void
    {
        $this->changeset->artifact = $artifact;
    }
}
