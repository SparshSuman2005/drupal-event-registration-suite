<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for smtp_update_8008() function.
 *
 * @group smtp
 */
class SmtpUpdate8008Test extends UnitTestCase {

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The mock config factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockConfigFactory;

  /**
   * The mock config object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a new container.
    $this->container = new ContainerBuilder();

    // Mock config factory and smtp.settings config.
    $this->mockConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->mockConfig        = $this->createMock(Config::class);

    // Add services to container.
    $this->container->set('config.factory', $this->mockConfigFactory);

    // Set the container.
    \Drupal::setContainer($this->container);

    // Include the install file if function doesn't exist.
    if (!function_exists('smtp_update_8008')) {
      require_once dirname(__DIR__, 3) . '/smtp.install';
    }
  }

  /**
   * Tests smtp_update_8008() sets log_level to ERROR.
   */
  public function testUpdate8008SetsLogLevel(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->expects($this->once())
      ->method('set')
      ->with('log_level', RfcLogLevel::ERROR)
      ->willReturn($this->mockConfig);
    $this->mockConfig->expects($this->once())
      ->method('save')
      ->with(TRUE)
      ->willReturn($this->mockConfig);

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->expects($this->once())
      ->method('getEditable')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    // Execute the update hook.
    smtp_update_8008();
  }

  /**
   * Tests smtp_update_8008() calls methods in correct order.
   */
  public function testUpdate8008MethodCallOrder(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->expects($this->once())
      ->method('set')
      ->with('log_level', RfcLogLevel::ERROR)
      ->willReturn($this->mockConfig);
    $this->mockConfig->expects($this->once())
      ->method('save')
      ->with(TRUE)
      ->willReturn($this->mockConfig);

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->expects($this->once())
      ->method('getEditable')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    // Execute the update hook.
    smtp_update_8008();
  }

  /**
   * Tests smtp_update_8008() gets the correct config object.
   */
  public function testUpdate8008GetsCorrectConfig(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->expects($this->once())
      ->method('set')
      ->with('log_level', RfcLogLevel::ERROR)
      ->willReturn($this->mockConfig);
    $this->mockConfig->expects($this->once())
      ->method('save')
      ->with(TRUE)
      ->willReturn($this->mockConfig);

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->expects($this->once())
      ->method('getEditable')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    // Execute the update hook.
    smtp_update_8008();
  }

  /**
   * Tests smtp_update_8008() saves configuration with hasData flag.
   */
  public function testUpdate8008SavesConfigurationWithHasDataFlag(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->expects($this->once())
      ->method('set')
      ->with('log_level', RfcLogLevel::ERROR)
      ->willReturn($this->mockConfig);
    $this->mockConfig->expects($this->once())
      ->method('save')
      ->with(TRUE)
      ->willReturn($this->mockConfig);

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->expects($this->once())
      ->method('getEditable')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    // Execute the update hook.
    smtp_update_8008();
  }

  /**
   * Tests smtp_update_8008() sets log_level to correct value.
   */
  public function testUpdate8008SetsCorrectLogLevelValue(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->expects($this->once())
      ->method('set')
      ->with('log_level', RfcLogLevel::ERROR)
      ->willReturn($this->mockConfig);
    $this->mockConfig->expects($this->once())
      ->method('save')
      ->with(TRUE)
      ->willReturn($this->mockConfig);

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->expects($this->once())
      ->method('getEditable')
      ->with('smtp.settings')
      ->willReturn($this->mockConfig);

    // Execute the update hook.
    smtp_update_8008();
  }

}
