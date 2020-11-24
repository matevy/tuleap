<?php
/**
 * Copyright (c) Enalean, 2016 - Present. All Rights Reserved.
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

namespace Tuleap\OpenIDConnectClient\Authentication;

use Firebase\JWT\JWT;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\OpenIDConnectClient\Provider\Provider;

require_once(__DIR__ . '/../bootstrap.php');

class IDTokenVerifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $rsa_key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rsa_key = openssl_pkey_new(
            array(
                'digest_alg'       => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA
            )
        );
    }

    public function testItRejectsIDTokenIfPartsAreMissingInTheJWT(): void
    {
        $provider          = \Mockery::mock(Provider::class);
        $nonce             = 'random_string';
        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $fake_id_token     = 'aaaaa.aaaaa';

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $fake_id_token);
    }

    public function testItRejectsIDTokenIfPayloadCantBeRead(): void
    {
        $provider          = \Mockery::mock(Provider::class);
        $nonce             = 'random_string';
        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $fake_id_token     = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.' .
            'fail.' .
            'EkN-DOsnsuRjRO6BxXemmJDm3HbxrbRzXglbN2S4sOkopdU4IsDxTI8jO19W_A4K8ZPJijNLis4EZsHeY559a4DFOd50_OqgHGuERTqY' .
            'ZyuhtF39yxJPAjUESwxk2J5k_4zM3O-vtd1Ghyo4IbqKKSy6J9mTniYJPenn5-HIirE';

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $fake_id_token);
    }

    public function testItRejectsIDTokenIfSubjectIdentifierIsNotPresent(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $id_token          = JWT::encode(
            array(
                'iss' => 'example.com',
                'aud' => 'client_id',
            ),
            $this->rsa_key,
            'RS256'
        );

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $id_token);
    }

    public function testItRejectsIDTokenIfAudienceClaimIsInvalid(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $id_token          = JWT::encode(
            array(
                'iss' => 'example.com',
                'aud' => 'evil_client_id',
                'sub' => '123'
            ),
            $this->rsa_key,
            'RS256'
        );

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $id_token);
    }

    public function testItRejectsIDTokenIfAudienceClaimIsNotPresentInTheList(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $id_token          = JWT::encode(
            array(
                'iss' => 'example.com',
                'aud' => array('evil0_client_id', 'evil1_client_id'),
                'sub' => '123'
            ),
            $this->rsa_key,
            'RS256'
        );

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $id_token);
    }

    public function testItRejectsIDTokenIfIssuerIdentifierIsInvalid(): void
    {
        $provider = \Mockery::spy(Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorInvalid());
        $id_token          = JWT::encode(
            array(
                'nonce' => $nonce,
                'iss'   => 'evil.example.com',
                'aud'   => 'client_id',
                'sub'   => '123'
            ),
            $this->rsa_key,
            'RS256'
        );

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $id_token);
    }

    public function testItRejectsIDTokenIfNonceIsInvalid(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());
        $id_token          = JWT::encode(
            array(
                'nonce' => 'different_random_string',
                'iss'   => 'evil.example.com',
                'aud'   => 'client_id',
                'sub'   => '123'
            ),
            $this->rsa_key,
            'RS256'
        );

        $this->expectException('Tuleap\OpenIDConnectClient\Authentication\MalformedIDTokenException');
        $id_token_verifier->validate($provider, $nonce, $id_token);
    }

    public function testItAcceptsAValidIDToken(): void
    {
        $provider = \Mockery::mock(\Tuleap\OpenIDConnectClient\Provider\Provider::class);
        $provider->shouldReceive('getAuthorizationEndpoint')->andReturns('https://example.com/oauth2/auth');
        $provider->shouldReceive('getClientId')->andReturns('client_id_2');
        $nonce    = 'random_string';

        $id_token_verifier = new IDTokenVerifier($this->generateIssuerValidatorValid());

        $id_token_content  = array(
            'nonce' => $nonce,
            'iss'   => 'example.com',
            'aud'   => array('client_id_1', 'client_id_2'),
            'sub'   => '123'
        );
        $id_token          = JWT::encode(
            $id_token_content,
            $this->rsa_key,
            'RS256'
        );

        $verified_id_token = $id_token_verifier->validate($provider, $nonce, $id_token);
        $this->assertSame($verified_id_token, $id_token_content);
    }

    private function generateIssuerValidatorValid() : IssuerClaimValidator
    {
        return new class implements IssuerClaimValidator
        {
            public function isIssuerClaimValid(Provider $provider, string $iss_from_id_token): bool
            {
                return true;
            }
        };
    }

    private function generateIssuerValidatorInvalid() : IssuerClaimValidator
    {
        return new class implements IssuerClaimValidator
        {
            public function isIssuerClaimValid(Provider $provider, string $iss_from_id_token): bool
            {
                return false;
            }
        };
    }
}
