<?php

namespace Tests\Unit;

use App\Config;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Config class to verify environment variable parsing,
 * default values, and offline mode detection.
 */
class ConfigTest extends TestCase
{
    public function testGetTablePrefixReturnsEnvValue(): void
    {
        $_ENV['TABLE_PREFIX'] = 'test_';
        $this->assertSame('test_', Config::getTablePrefix());
    }

    public function testGetTablePrefixReturnsEmptyStringWhenNotSet(): void
    {
        unset($_ENV['TABLE_PREFIX']);
        $this->assertSame('', Config::getTablePrefix());
    }

    public function testGetProjectNameReturnsEnvValue(): void
    {
        $_ENV['PROJECT_NAME'] = 'TAIPO Test';
        $this->assertSame('TAIPO Test', Config::getProjectName());
    }

    public function testGetMaxTitleLengthDefaultIs42(): void
    {
        unset($_ENV['MAX_TITLE_LENGTH']);
        $this->assertSame(42, Config::getMaxTitleLength());
    }

    public function testGetMaxTitleLengthReadsEnv(): void
    {
        $_ENV['MAX_TITLE_LENGTH'] = '100';
        $this->assertSame(100, Config::getMaxTitleLength());
    }

    public function testGetMaxDescriptionLengthDefaultIs512(): void
    {
        unset($_ENV['MAX_DESCRIPTION_LENGTH']);
        $this->assertSame(512, Config::getMaxDescriptionLength());
    }

    public function testGetMaxQueryLengthDefaultIs1320(): void
    {
        unset($_ENV['MAX_QUERY_LENGTH']);
        $this->assertSame(1320, Config::getMaxQueryLength());
    }

    public function testGetMinUsernameLengthDefaultIs4(): void
    {
        unset($_ENV['MIN_USERNAME_LENGTH']);
        $this->assertSame(4, Config::getMinUsernameLength());
    }

    public function testGetMinPasswordLengthDefaultIs8(): void
    {
        unset($_ENV['MIN_PASSWORD_LENGTH']);
        $this->assertSame(8, Config::getMinPasswordLength());
    }

    public function testIsRegistrationEnabledDefaultIsTrue(): void
    {
        unset($_ENV['REGISTRATION_ENABLED']);
        $this->assertTrue(Config::isRegistrationEnabled());
    }

    public function testIsRegistrationEnabledCanBeDisabled(): void
    {
        $_ENV['REGISTRATION_ENABLED'] = 'false';
        $this->assertFalse(Config::isRegistrationEnabled());
    }

    public function testSupportedLanguagesIsNotEmpty(): void
    {
        $this->assertNotEmpty(Config::SUPPORTED_LANGUAGES);
        $this->assertContains('PHP', Config::SUPPORTED_LANGUAGES);
        $this->assertContains('Python', Config::SUPPORTED_LANGUAGES);
        $this->assertContains('Java', Config::SUPPORTED_LANGUAGES);
    }

    public function testAppJsonConstant(): void
    {
        $this->assertSame('Content-Type: application/json', Config::APP_JSON);
    }

    public function testGetDatabaseConfigReturnsExpectedKeys(): void
    {
        $config = Config::getDatabaseConfig();
        $this->assertArrayHasKey('sqlite_file', $config);
        $this->assertArrayHasKey('type', $config);
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('dbname', $config);
        $this->assertArrayHasKey('user', $config);
        $this->assertArrayHasKey('password', $config);
        $this->assertArrayHasKey('port', $config);
    }
}
