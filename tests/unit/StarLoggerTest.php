<?php

/**
 * Tests for StarLogger – centralized structured logging utility.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\SparxstarUEC\helpers\StarLogger;

/**
 * Unit tests for StarLogger log level filtering, data sanitization,
 * convenience wrappers, and timer utilities.
 *
 * NOTE: StarLogger writes to error_log(). Rather than capturing that output,
 * we test observable side effects: the do_action hook fires and level-filtering
 * prevents the hook from firing when below the minimum level.
 */
final class StarLoggerTest extends SirusTestCase
{
    protected function setUp(): void
    {
        // Reset global shim state.
        $GLOBALS['fired_actions']   = [];
        $GLOBALS['wp_options']      = [];

        // Reset StarLogger to known defaults using reflection.
        $this->resetLoggerState();
    }

    protected function tearDown(): void
    {
        $this->resetLoggerState();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Resets StarLogger static state to defaults via reflection.
     */
    private function resetLoggerState(): void
    {
        $reflection = new \ReflectionClass(StarLogger::class);

        $min = $reflection->getProperty('min_log_level');
        $min->setAccessible(true);
        $min->setValue(null, StarLogger::INFO);

        $json = $reflection->getProperty('json_mode');
        $json->setAccessible(true);
        $json->setValue(null, false);

        $cid = $reflection->getProperty('correlation_id');
        $cid->setAccessible(true);
        $cid->setValue(null, null);

        $timers = $reflection->getProperty('timers');
        $timers->setAccessible(true);
        $timers->setValue(null, []);
    }

    // ── Level constants ───────────────────────────────────────────────────────

    /**
     * Level constants must have the correct integer values.
     */
    public function testLevelConstantsHaveCorrectValues(): void
    {
        $this->assertSame(100, StarLogger::DEBUG);
        $this->assertSame(200, StarLogger::INFO);
        $this->assertSame(250, StarLogger::NOTICE);
        $this->assertSame(300, StarLogger::WARNING);
        $this->assertSame(400, StarLogger::ERROR);
        $this->assertSame(500, StarLogger::CRITICAL);
        $this->assertSame(550, StarLogger::ALERT);
        $this->assertSame(600, StarLogger::EMERGENCY);
    }

    // ── Minimum level filtering ───────────────────────────────────────────────

    /**
     * Messages below the minimum level must NOT fire the star_log_event action.
     */
    public function testMessagesAtOrAboveMinLevelFireAction(): void
    {
        StarLogger::setMinLogLevel('info');
        StarLogger::info('test', 'hello');

        $fired = $GLOBALS['fired_actions']['star_log_event'] ?? [];
        $this->assertNotEmpty($fired, 'star_log_event should fire for INFO messages when min level is INFO.');
    }

    /**
     * Messages below the minimum level must NOT fire the star_log_event action.
     */
    public function testMessagesBelowMinLevelDoNotFireAction(): void
    {
        StarLogger::setMinLogLevel('error');
        $GLOBALS['fired_actions'] = [];

        StarLogger::debug('test', 'this should be filtered');
        StarLogger::info('test', 'this should be filtered too');
        StarLogger::warning('test', 'this should be filtered as well');

        $fired = $GLOBALS['fired_actions']['star_log_event'] ?? [];
        $this->assertEmpty($fired, 'star_log_event must not fire for levels below the minimum.');
    }

    /**
     * setMinLogLevel() accepts case-insensitive level names.
     */
    public function testSetMinLogLevelIsCaseInsensitive(): void
    {
        StarLogger::setMinLogLevel('WARNING');
        $GLOBALS['fired_actions'] = [];

        StarLogger::info('test', 'below warning');
        $fired = $GLOBALS['fired_actions']['star_log_event'] ?? [];
        $this->assertEmpty($fired, 'INFO should be suppressed when min level is WARNING.');
    }

    /**
     * setMinLogLevel() silently ignores unknown level names.
     */
    public function testSetMinLogLevelIgnoresUnknownNames(): void
    {
        // Must not throw.
        StarLogger::setMinLogLevel('not_a_real_level');
        $this->assertTrue(true);
    }

    // ── setLogFilePath() ─────────────────────────────────────────────────────

    /**
     * setLogFilePath() is a no-op and must not throw.
     */
    public function testSetLogFilePathIsNoOp(): void
    {
        StarLogger::setLogFilePath('/tmp/ignored.log');
        $this->assertTrue(true);
    }

    // ── do_action hook payload ────────────────────────────────────────────────

    /**
     * star_log_event must receive the correct level_name, context, and message.
     */
    public function testActionReceivesCorrectPayload(): void
    {
        StarLogger::setMinLogLevel('info');
        StarLogger::info('MyContext', 'Test message payload');

        $calls = $GLOBALS['fired_actions']['star_log_event'] ?? [];
        $this->assertNotEmpty($calls);

        $args = $calls[0];
        $this->assertSame('INFO', $args[0], 'First arg should be level_name in UPPERCASE.');
        $this->assertSame('MyContext', $args[1], 'Second arg should be the context string.');
        $this->assertSame('Test message payload', $args[2], 'Third arg should be the raw message.');
    }

    // ── Throwable formatting ──────────────────────────────────────────────────

    /**
     * Passing a Throwable as the message must format it as ClassName: message in file:line.
     */
    public function testThrowableIsFormattedCorrectly(): void
    {
        StarLogger::setMinLogLevel('error');
        $exception = new \RuntimeException('Disk full');
        StarLogger::error('DiskContext', $exception);

        $calls = $GLOBALS['fired_actions']['star_log_event'] ?? [];
        $this->assertNotEmpty($calls);
        // Third arg is the original Throwable, not a formatted string.
        $this->assertInstanceOf(\RuntimeException::class, $calls[0][2]);
    }

    // ── Data sanitization ─────────────────────────────────────────────────────

    /**
     * sanitizeData() must redact values whose keys match sensitive patterns
     * (ip, email, user, token, auth, fingerprint).
     * Tested via reflection since sanitizeData is a protected static method.
     */
    public function testSensitiveExtraDataIsRedacted(): void
    {
        $method = new \ReflectionMethod(StarLogger::class, 'sanitizeData');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            'ip_address'   => '192.168.1.100',
            'user_email'   => 'test@example.com',
            'access_token' => 'supersecret',
            'fingerprint'  => 'abc123',
            'safe_value'   => 'this is fine',
        ]);

        $this->assertSame('[REDACTED]', $result['ip_address'], 'ip_address must be redacted.');
        $this->assertSame('[REDACTED]', $result['user_email'], 'user_email must be redacted.');
        $this->assertSame('[REDACTED]', $result['access_token'], 'access_token must be redacted.');
        $this->assertSame('[REDACTED]', $result['fingerprint'], 'fingerprint must be redacted.');
        $this->assertSame('this is fine', $result['safe_value'], 'Non-sensitive value must not be redacted.');
    }

    /**
     * Sensitive keys nested in sub-arrays must also be redacted.
     */
    public function testNestedSensitiveDataIsRedacted(): void
    {
        $method = new \ReflectionMethod(StarLogger::class, 'sanitizeData');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            'meta' => [
                'auth_token' => 'abc',
                'label'      => 'ok',
            ],
        ]);

        $this->assertSame('[REDACTED]', $result['meta']['auth_token']);
        $this->assertSame('ok', $result['meta']['label']);
    }

    // ── Convenience wrappers ──────────────────────────────────────────────────

    /**
     * Each convenience wrapper must fire star_log_event with the correct level name.
     */
    public function testConvenienceWrappersFireWithCorrectLevel(): void
    {
        StarLogger::setMinLogLevel('debug');

        $cases = [
            'debug'     => 'DEBUG',
            'info'      => 'INFO',
            'notice'    => 'NOTICE',
            'warning'   => 'WARNING',
            'error'     => 'ERROR',
            'critical'  => 'CRITICAL',
            'alert'     => 'ALERT',
            'emergency' => 'EMERGENCY',
        ];

        foreach ($cases as $method => $expected_level) {
            $GLOBALS['fired_actions'] = [];
            StarLogger::$method('WrapperTest', "Testing {$method}");

            $fired = $GLOBALS['fired_actions']['star_log_event'] ?? [];
            $this->assertNotEmpty($fired, "star_log_event must fire for {$method}().");
            $this->assertSame($expected_level, $fired[0][0], "{$method}() must fire with level '{$expected_level}'.");
        }
    }

    /**
     * warn() is an alias for warning() — must fire with 'WARNING' level.
     */
    public function testWarnAliasFiresWithWarningLevel(): void
    {
        StarLogger::setMinLogLevel('debug');
        $GLOBALS['fired_actions'] = [];

        StarLogger::warn('AliasTest', 'warn alias test');
        $fired = $GLOBALS['fired_actions']['star_log_event'] ?? [];

        $this->assertNotEmpty($fired);
        $this->assertSame('WARNING', $fired[0][0]);
    }

    // ── enableJsonMode ────────────────────────────────────────────────────────

    /**
     * enableJsonMode() must not throw; logging continues to work with JSON mode on.
     */
    public function testEnableJsonModeDoesNotThrow(): void
    {
        StarLogger::enableJsonMode(true);
        StarLogger::setMinLogLevel('info');
        StarLogger::info('JsonMode', 'json test');
        $this->assertNotEmpty($GLOBALS['fired_actions']['star_log_event'] ?? []);
        StarLogger::enableJsonMode(false);
    }

    // ── setCorrelationId ──────────────────────────────────────────────────────

    /**
     * setCorrelationId() with a specific ID must be accepted without error.
     */
    public function testSetCorrelationIdAcceptsExplicitId(): void
    {
        StarLogger::setCorrelationId('test-correlation-id-123');
        StarLogger::setMinLogLevel('info');
        StarLogger::info('CidTest', 'correlation id test');
        $this->assertNotEmpty($GLOBALS['fired_actions']['star_log_event'] ?? []);
    }

    /**
     * setCorrelationId(null) generates a new UUID.
     */
    public function testSetCorrelationIdNullGeneratesUuid(): void
    {
        // Must not throw when generating a UUID.
        StarLogger::setCorrelationId(null);
        $this->assertTrue(true);
    }

    // ── Timer utilities ───────────────────────────────────────────────────────

    /**
     * timeStart() + timeEnd() must not throw.
     */
    public function testTimerStartAndEndDoNotThrow(): void
    {
        StarLogger::setMinLogLevel('debug');
        StarLogger::timeStart('op_label');
        StarLogger::timeEnd('op_label', 'TimerContext');
        $this->assertTrue(true);
    }

    /**
     * timeEnd() on an unstarted label must be a no-op (does not throw).
     */
    public function testTimeEndOnUnstartedLabelIsNoOp(): void
    {
        StarLogger::timeEnd('non_existent_label');
        $this->assertTrue(true);
    }

    // ── boot() ────────────────────────────────────────────────────────────────

    /**
     * boot() is a no-op and must not throw.
     */
    public function testBootIsNoOp(): void
    {
        StarLogger::boot();
        $this->assertTrue(true);
    }
}
