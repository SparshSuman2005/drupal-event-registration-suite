<?php

declare(strict_types=1);

namespace Drupal\smtp\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * The SMTP logger.
 */
class SmtpLogger extends LoggerChannel implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The log level.
   *
   * @var int
   */
  protected $logLevel;

  /**
   * Constructs a SmtpLogger object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelInterface $logger) {
    $this->logLevel = $config_factory->get('smtp.settings')->get('log_level') ?? RfcLogLevel::ERROR;
    $this->addLogger($logger);
    $this->channel = 'smtp';
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, \Stringable|string $message, array $context = []): void {
    if ($this->callDepth == self::MAX_CALL_DEPTH) {
      return;
    }
    $this->callDepth++;
    // Merge in defaults.
    $context += [
      'channel' => $this->channel,
      'link' => '',
      'uid' => 0,
      'request_uri' => '',
      'referer' => '',
      'ip' => '',
      'timestamp' => time(),
    ];
    // Some context values are only available when in a request context.
    if ($this->requestStack && $request = $this->requestStack
      ->getCurrentRequest()) {
      $context['request_uri'] = $request->getUri();
      $context['referer'] = $request->headers
        ->get('Referer', '');
      $context['ip'] = $request->getClientIP() ?: '';
      if ($this->currentUser) {
        $context['uid'] = $this->currentUser
          ->id();
      }
    }
    if (is_string($level)) {
      // Convert to integer equivalent for consistency with RFC 5424.
      $level = $this->levelTranslation[$level];
    }

    if ($level <= $this->logLevel) {
      // Call all available loggers.
      foreach ($this->sortLoggers() as $logger) {
        $logger->log($level, $message, $context);
      }
    }

    $this->callDepth--;

  }

}
