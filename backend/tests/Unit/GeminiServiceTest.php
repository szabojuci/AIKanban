<?php

namespace Tests\Unit;

use App\Exception\GeminiApiException;
use App\Service\GeminiService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GeminiService: API key validation, request payload building,
 * and error handling. Does NOT make real API calls.
 */
class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['GEMINI_API_KEY'] = 'AIzaSyTEST_FAKE_KEY';
        $_ENV['GEMINI_BASE_MODEL'] = 'test-model';
        $_ENV['GEMINI_FALLBACK_MODEL'] = 'test-fallback';
        $_ENV['GEMINI_BASE_URL'] = 'https://example.com/api';
        $_ENV['GEMINI_FALLBACK_URL'] = 'https://example.com/api';
        $_ENV['GEMINI_TEMPERATURE'] = '0.5';
        $_ENV['GEMINI_TOP_K'] = '40';
        $_ENV['GEMINI_TOP_P'] = '0.9';
        $_ENV['GEMINI_MAX_OUTPUT_TOKENS'] = '1024';
    }

    public function testConstructorThrowsOnMissingApiKey(): void
    {
        $_ENV['GEMINI_API_KEY'] = '';
        try {
            $service = new GeminiService(null);
            $this->assertNotNull($service);
            $this->fail('Expected GeminiApiException was not thrown');
        } catch (GeminiApiException $e) {
            $this->assertStringContainsString('API key is not set or invalid', $e->getMessage());
        }
    }

    public function testConstructorThrowsOnInvalidApiKeyFormat(): void
    {
        $_ENV['GEMINI_API_KEY'] = 'INVALID_KEY_FORMAT';
        try {
            $service = new GeminiService(null);
            $this->assertNotNull($service);
            $this->fail('Expected GeminiApiException was not thrown');
        } catch (GeminiApiException $e) {
            // Success
            $this->assertTrue(true);
        }
    }

    public function testConstructorSucceedsWithValidKey(): void
    {
        $service = new GeminiService(null);
        $this->assertInstanceOf(GeminiService::class, $service);
    }

    public function testSetContextDoesNotThrow(): void
    {
        $service = new GeminiService(null);
        $service->setContext(1, 2);
        // No exception means success
        $this->assertTrue(true);
    }

    public function testGetAggregatedApiUsageReturnsEmptyWithoutPdo(): void
    {
        $service = new GeminiService(null);
        $result = $service->getAggregatedApiUsage();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAggregatedApiUsageWithPdoAndData(): void
    {
        $_ENV['TABLE_PREFIX'] = '';

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $config = \App\Configuration\DatabaseConfig::get();
        foreach ($config['schema'] as $sql) {
            $pdo->exec($sql);
        }

        // Insert usage data
        $pdo->exec("INSERT INTO api_usage (endpoint, prompt_tokens, candidate_tokens, total_tokens, user_id) VALUES ('test-model', 100, 50, 150, 1)");
        $pdo->exec("INSERT INTO api_usage (endpoint, prompt_tokens, candidate_tokens, total_tokens, user_id) VALUES ('test-model', 200, 100, 300, 1)");

        $service = new GeminiService($pdo);
        $result = $service->getAggregatedApiUsage(true);

        $this->assertCount(1, $result);
        $this->assertSame('test-model', $result[0]['model']);
        $this->assertSame(300, $result[0]['prompt_tokens']);
        $this->assertSame(150, $result[0]['candidate_tokens']);
        $this->assertSame(450, $result[0]['total_tokens']);
    }

    public function testGetAggregatedApiUsageFiltersForNonInstructor(): void
    {
        $_ENV['TABLE_PREFIX'] = '';

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $config = \App\Configuration\DatabaseConfig::get();
        foreach ($config['schema'] as $sql) {
            $pdo->exec($sql);
        }

        $pdo->exec("INSERT INTO api_usage (endpoint, prompt_tokens, candidate_tokens, total_tokens, user_id) VALUES ('model', 100, 50, 150, 1)");
        $pdo->exec("INSERT INTO api_usage (endpoint, prompt_tokens, candidate_tokens, total_tokens, user_id) VALUES ('model', 200, 100, 300, 2)");

        $service = new GeminiService($pdo);

        // Non-instructor, user_id=1 should only see own usage
        $result = $service->getAggregatedApiUsage(false, 1, []);
        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]['prompt_tokens']);
    }
}
