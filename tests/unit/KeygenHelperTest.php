<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit tests for the pure functions in app/Helpers/keygen_helper.php.
 *
 * These don't touch the database, network or session — they lock in the exact
 * behaviour of the bits that are easy to break silently (key format/entropy,
 * duration labels, level-CSV parsing, JSON escaping, expiry rules).
 *
 * @internal
 */
final class KeygenHelperTest extends CIUnitTestCase
{
    // ---------------------------------------------------------------- kg_duration

    public function testDurationShortAndLongFormats(): void
    {
        $this->assertSame('30d', kg_duration(720));
        $this->assertSame('30 days', kg_duration(720, true));

        $this->assertSame('12h', kg_duration(12));
        $this->assertSame('12 hours', kg_duration(12, true));

        $this->assertSame('1d', kg_duration(24));
        $this->assertSame('1 day', kg_duration(24, true));

        $this->assertSame('1h', kg_duration(1));
        $this->assertSame('1 hour', kg_duration(1, true));
    }

    public function testDurationTreatsNonPositiveAsDash(): void
    {
        $this->assertSame('—', kg_duration(0));
        $this->assertSame('—', kg_duration(-5));
    }

    // ----------------------------------------------------------- normalize_key_part

    public function testNormalizeKeyPartStripsEverythingButUppercaseAlnum(): void
    {
        $this->assertSame('ABCD', normalize_key_part('ab-cd!'));
        $this->assertSame('A1B2', normalize_key_part(' a1_b2 '));
        $this->assertSame('', normalize_key_part('---'));
    }

    public function testNormalizeKeyPartRespectsTheLengthCap(): void
    {
        $this->assertSame('ABCDE', normalize_key_part('abcdefghij', 5));
    }

    // ------------------------------------------------------ parse_level_list

    public function testParseLevelListDropsBlanksDuplicatesAndNonPositive(): void
    {
        $this->assertSame([2, 3, 1], parse_level_list('2,3,3,1,x,0'));
    }

    public function testParseLevelListHandlesEmptyInput(): void
    {
        $this->assertSame([], parse_level_list(null));
        $this->assertSame([], parse_level_list(''));
        $this->assertSame([], parse_level_list('   '));
        $this->assertSame([], parse_level_list('0,-4'));
    }

    // ------------------------------------------------------- kg_json (XSS safety)

    public function testKgJsonNeutralisesScriptBreakoutCharacters(): void
    {
        $json = kg_json('</script><img src=x onerror=alert(1)>');

        // No raw angle brackets / quotes that could terminate the <script> tag.
        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringNotContainsString('<img', $json);
        $this->assertStringContainsString('\u003C', $json); // escaped "<"

        // Round-trips back to the original value.
        $this->assertSame(
            '</script><img src=x onerror=alert(1)>',
            json_decode($json, true),
        );
    }

    public function testKgJsonFallsBackToNullOnEncodingFailure(): void
    {
        // Invalid UTF-8 can't be encoded → helper must return the literal null.
        $this->assertSame('null', kg_json("\xB1\x31"));
    }

    // ------------------------------------------------------- key_is_expired / banned

    public function testUnusedKeysNeverExpire(): void
    {
        $this->assertFalse(key_is_expired([
            'status'     => 'unused',
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]));
    }

    public function testUsedKeyWithPastExpiryIsExpired(): void
    {
        $this->assertTrue(key_is_expired([
            'status'     => 'used',
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]));
    }

    public function testUsedKeyWithFutureOrMissingExpiryIsNotExpired(): void
    {
        $this->assertFalse(key_is_expired([
            'status'     => 'used',
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]));
        $this->assertFalse(key_is_expired(['status' => 'used']));
        $this->assertFalse(key_is_expired(['status' => 'used', 'expires_at' => null]));
    }

    public function testKeyIsBannedOnlyForBannedStatus(): void
    {
        $this->assertTrue(key_is_banned(['status' => 'banned']));
        $this->assertFalse(key_is_banned(['status' => 'used']));
        $this->assertFalse(key_is_banned([]));
    }

    // ------------------------------------------------- generate_license_key_for

    public function testGeneratedKeyStartsWithNormalisedUsername(): void
    {
        $key = generate_license_key_for('acme');
        $this->assertStringStartsWith('ACME-', $key);
    }

    public function testGeneratedKeyHasConstantLengthForShortUsername(): void
    {
        // head "ACME" + "-" + 24-char tail = 29.
        $this->assertSame(29, strlen(generate_license_key_for('ACME')));
        $this->assertSame(29, strlen(generate_license_key_for('ACME', 'BATCH01')));
    }

    public function testGeneratedKeyOnlyUsesTheSafeAlphabet(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $key = generate_license_key_for('SELLER', 'TAG');
            $this->assertMatchesRegularExpression('/^[A-Z0-9-]+$/', $key);
            $this->assertStringNotContainsString('--', $key);
            $this->assertStringEndsNotWith('-', $key);
        }
    }

    public function testGeneratedKeysAreEffectivelyUnique(): void
    {
        $keys = [];
        for ($i = 0; $i < 200; $i++) {
            $keys[] = generate_license_key_for('SHOP');
        }
        $this->assertCount(200, array_unique($keys));
    }

    public function testLongerHeadStillKeepsAtLeast12RandomChars(): void
    {
        // 16-char user + 16-char tag would blow past the 29-char target, so the
        // tail is floored at 12 (≈60 bits) instead of collapsing to nothing.
        $user = 'AAAAAAAAAAAAAAAA';
        $tag  = 'BBBBBBBBBBBBBBBB';
        $key  = generate_license_key_for($user, $tag);

        $head = $user . '-' . $tag;

        $this->assertStringStartsWith($head . '-', $key);

        $tail = substr($key, strlen($head) + 1);
        // The floor is 12 tail chars; with the row-grouping dash that is
        // ~11 random alphabet chars (≈55 bits), never a guessable stub.
        $this->assertGreaterThanOrEqual(12, strlen($tail));
        $this->assertGreaterThanOrEqual(11, strlen(str_replace('-', '', $tail)));
        $this->assertGreaterThan(29, strlen($key));
    }

    // ---------------------------------------------------------------- hash_api_key

    public function testHashApiKeyIsDeterministicAndDistinct(): void
    {
        $a = hash_api_key('key-one');
        $b = hash_api_key('key-one');
        $c = hash_api_key('key-two');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a)); // 256-bit hex digest
    }

    public function testGeneratedApiKeyIsRandomAndLong(): void
    {
        $a = generate_api_key();
        $b = generate_api_key();

        $this->assertNotSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
    }
}
