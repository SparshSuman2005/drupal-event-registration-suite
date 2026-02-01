<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Logger\SmtpLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for SmtpLogger.
 *
 * @group smtp
 * @coversDefaultClass \Drupal\smtp\Logger\SmtpLogger
 */
class SmtpLoggerTest extends UnitTestCase {

  /**
   * The mock config factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockConfigFactory;

  /**
   * The mock config.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockConfig;

  /**
   * The mock logger channel.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockLoggerChannel;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mockConfig = $this->createMock(ImmutableConfig::class);
    $this->mockConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->mockLoggerChannel = $this->createMock(LoggerChannelInterface::class);
  }

  /**
   * Tests constructor with configured log level.
   *
   * @covers ::__construct
   */
  public function testConstructorWithLogLevel(): void {
    $logLevel = RfcLogLevel::WARNING;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    // Use reflection to verify the log level was set correctly.
    $reflection = new \ReflectionClass($logger);
    $property = $reflection->getProperty('logLevel');
    $property->setAccessible(TRUE);
    $this->assertEquals($logLevel, $property->getValue($logger));
  }

  /**
   * Tests constructor with default log level when config is null.
   *
   * @covers ::__construct
   */
  public function testConstructorWithDefaultLogLevel(): void {
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn(NULL);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    // Use reflection to verify the default log level was set.
    $reflection = new \ReflectionClass($logger);
    $property = $reflection->getProperty('logLevel');
    $property->setAccessible(TRUE);
    $this->assertEquals(RfcLogLevel::ERROR, $property->getValue($logger));
  }

  /**
   * Tests log method logs when level is less than or equal to configured level.
   *
   * @covers ::log
   */
  public function testLogWhenLevelIsLessThanOrEqual(): void {
    $logLevel = RfcLogLevel::WARNING;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    $message = 'Test warning message';
    $context = ['test' => 'value'];

    // Track the calls to verify both log calls happen with correct levels.
    $calls = [];
    $this->mockLoggerChannel->expects($this->exactly(2))
      ->method('log')
      ->willReturnCallback(function ($level, $msg, $ctx) use (&$calls) {
        $calls[] = $level;
      });

    $logger->log(RfcLogLevel::WARNING, $message, $context);
    $logger->log(RfcLogLevel::ERROR, $message, $context);

    // Verify both calls happened with correct levels.
    $this->assertCount(2, $calls);
    $this->assertContains(RfcLogLevel::WARNING, $calls);
    $this->assertContains(RfcLogLevel::ERROR, $calls);
  }

  /**
   * Tests log method does not log when level is greater than configured level.
   *
   * @covers ::log
   */
  public function testLogWhenLevelIsGreaterThan(): void {
    $logLevel = RfcLogLevel::WARNING;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    $message = 'Test notice message';
    $context = ['test' => 'value'];

    // NOTICE level should NOT be logged (greater than configured level).
    $this->mockLoggerChannel->expects($this->never())
      ->method('log');

    $logger->log(RfcLogLevel::NOTICE, $message, $context);
  }

  /**
   * Tests log method converts string log levels to integers.
   *
   * @covers ::log
   */
  public function testLogConvertsStringLevels(): void {
    $logLevel = RfcLogLevel::ERROR;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    $message = 'Test error message';
    $context = [];

    // String 'error' should be converted to RfcLogLevel::ERROR.
    $this->mockLoggerChannel->expects($this->once())
      ->method('log')
      ->with(
        $this->equalTo(RfcLogLevel::ERROR),
        $this->equalTo($message),
        $this->anything()
      );

    $logger->log('error', $message, $context);
  }

  /**
   * Tests log method merges context with defaults.
   *
   * @covers ::log
   */
  public function testLogMergesContextWithDefaults(): void {
    $logLevel = RfcLogLevel::ERROR;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    $message = 'Test message';
    $context = ['custom' => 'value'];

    $this->mockLoggerChannel->expects($this->once())
      ->method('log')
      ->with(
        $this->equalTo(RfcLogLevel::ERROR),
        $this->equalTo($message),
        $this->callback(function ($mergedContext) {
          return isset($mergedContext['channel']) &&
                 $mergedContext['channel'] === 'smtp' &&
                 isset($mergedContext['link']) &&
                 $mergedContext['link'] === '' &&
                 isset($mergedContext['uid']) &&
                 $mergedContext['uid'] === 0 &&
                 isset($mergedContext['request_uri']) &&
                 $mergedContext['request_uri'] === '' &&
                 isset($mergedContext['referer']) &&
                 $mergedContext['referer'] === '' &&
                 isset($mergedContext['ip']) &&
                 $mergedContext['ip'] === '' &&
                 isset($mergedContext['timestamp']) &&
                 isset($mergedContext['custom']) &&
                 $mergedContext['custom'] === 'value';
        })
      );

    $logger->log(RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Tests log method adds request context when available.
   *
   * @covers ::log
   */
  public function testLogAddsRequestContext(): void {
    $logLevel = RfcLogLevel::ERROR;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    // Set up request stack and request using reflection.
    $requestStack = new RequestStack();
    $request = Request::create('http://example.com/test', 'GET', [], [], [], [
      'HTTP_REFERER' => 'http://example.com/referer',
    ]);
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $requestStack->push($request);

    $reflection = new \ReflectionClass($logger);
    $requestStackProperty = $reflection->getProperty('requestStack');
    $requestStackProperty->setAccessible(TRUE);
    $requestStackProperty->setValue($logger, $requestStack);

    $message = 'Test message';
    $context = [];

    $this->mockLoggerChannel->expects($this->once())
      ->method('log')
      ->with(
        $this->equalTo(RfcLogLevel::ERROR),
        $this->equalTo($message),
        $this->callback(function ($mergedContext) {
          return isset($mergedContext['request_uri']) &&
                 $mergedContext['request_uri'] === 'http://example.com/test' &&
                 isset($mergedContext['referer']) &&
                 $mergedContext['referer'] === 'http://example.com/referer' &&
                 isset($mergedContext['ip']) &&
                 $mergedContext['ip'] === '192.168.1.1';
        })
      );

    $logger->log(RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Tests log method adds user context when available.
   *
   * @covers ::log
   */
  public function testLogAddsUserContext(): void {
    $logLevel = RfcLogLevel::ERROR;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    // Set up request stack and current user using reflection.
    $requestStack = new RequestStack();
    $request = Request::create('http://example.com/test');
    $requestStack->push($request);

    $mockCurrentUser = $this->createMock(AccountProxyInterface::class);
    $mockCurrentUser->expects($this->once())
      ->method('id')
      ->willReturn(42);

    $reflection = new \ReflectionClass($logger);
    $requestStackProperty = $reflection->getProperty('requestStack');
    $requestStackProperty->setAccessible(TRUE);
    $requestStackProperty->setValue($logger, $requestStack);

    $currentUserProperty = $reflection->getProperty('currentUser');
    $currentUserProperty->setAccessible(TRUE);
    $currentUserProperty->setValue($logger, $mockCurrentUser);

    $message = 'Test message';
    $context = [];

    $this->mockLoggerChannel->expects($this->once())
      ->method('log')
      ->with(
        $this->equalTo(RfcLogLevel::ERROR),
        $this->equalTo($message),
        $this->callback(function ($mergedContext) {
          return isset($mergedContext['uid']) && $mergedContext['uid'] === 42;
        })
      );

    $logger->log(RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Tests log method handles call depth correctly.
   *
   * @covers ::log
   */
  public function testLogHandlesCallDepth(): void {
    $logLevel = RfcLogLevel::ERROR;
    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($logLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    // Set call depth to MAX_CALL_DEPTH using reflection.
    $reflection = new \ReflectionClass($logger);
    $callDepthProperty = $reflection->getProperty('callDepth');
    $callDepthProperty->setAccessible(TRUE);
    $maxCallDepth = $reflection->getConstant('MAX_CALL_DEPTH');
    $callDepthProperty->setValue($logger, $maxCallDepth);

    $message = 'Test message';
    $context = [];

    // Should not call logger when at max call depth.
    $this->mockLoggerChannel->expects($this->never())
      ->method('log');

    $logger->log(RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Tests log method with all log levels.
   *
   * @covers ::log
   * @dataProvider logLevelProvider
   */
  public function testLogWithAllLevels(int $configuredLevel, int $logLevel, bool $shouldLog): void {
    $this->mockConfig = $this->createMock(ImmutableConfig::class);
    $this->mockConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->mockLoggerChannel = $this->createMock(LoggerChannelInterface::class);

    $this->mockConfig->expects($this->once())
      ->method('get')
      ->with('log_level')
      ->willReturn($configuredLevel);
    $this->mockConfigFactory->expects($this->once())
      ->method('get')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    $logger = new SmtpLogger($this->mockConfigFactory, $this->mockLoggerChannel);

    $message = 'Test message';
    $context = [];

    if ($shouldLog) {
      $this->mockLoggerChannel->expects($this->once())
        ->method('log')
        ->with($logLevel, $message, $this->anything());
    }
    else {
      $this->mockLoggerChannel->expects($this->never())
        ->method('log');
    }

    $logger->log($logLevel, $message, $context);
  }

  /**
   * Data provider for testLogWithAllLevels.
   *
   * @return array
   *   Array of test cases with configured level, log level and expected result.
   */
  public static function logLevelProvider(): array {
    return [
      'ERROR configured, EMERGENCY logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::EMERGENCY,
        TRUE,
      ],
      'ERROR configured, ALERT logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::ALERT,
        TRUE,
      ],
      'ERROR configured, CRITICAL logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::CRITICAL,
        TRUE,
      ],
      'ERROR configured, ERROR logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::ERROR,
        TRUE,
      ],
      'ERROR configured, WARNING not logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::WARNING,
        FALSE,
      ],
      'ERROR configured, NOTICE not logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::NOTICE,
        FALSE,
      ],
      'ERROR configured, INFO not logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::INFO,
        FALSE,
      ],
      'ERROR configured, DEBUG not logged' => [
        RfcLogLevel::ERROR,
        RfcLogLevel::DEBUG,
        FALSE,
      ],
      'WARNING configured, WARNING logged' => [
        RfcLogLevel::WARNING,
        RfcLogLevel::WARNING,
        TRUE,
      ],
      'WARNING configured, NOTICE not logged' => [
        RfcLogLevel::WARNING,
        RfcLogLevel::NOTICE,
        FALSE,
      ],
      'DEBUG configured, DEBUG logged' => [
        RfcLogLevel::DEBUG,
        RfcLogLevel::DEBUG,
        TRUE,
      ],
    ];
  }

}
