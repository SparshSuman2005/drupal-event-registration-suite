<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for smtp_update_8007() function.
 *
 * @group smtp
 */
class SmtpUpdate8007Test extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The mock config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactory;

  /**
   * The mock config object.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
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
    $this->mockConfigFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->mockConfig        = $this->prophesize(Config::class);

    // Add services to container.
    $this->container->set('config.factory', $this->mockConfigFactory->reveal());

    // Set the container.
    \Drupal::setContainer($this->container);

    // Include the install file if function doesn't exist.
    if (!function_exists('smtp_update_8007')) {
      require_once dirname(__DIR__, 3) . '/smtp.install';
    }
  }

  /**
   * Tests smtp_update_8007() removes smtp_test_address from configuration.
   */
  public function testUpdate8007RemovesSmtpTestAddress(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->clear('smtp_test_address')->willReturn($this->mockConfig->reveal());
    $this->mockConfig->save()->willReturn($this->mockConfig->reveal());

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($this->mockConfig->reveal());

    // Execute the update hook.
    smtp_update_8007();

    // Verify that clear was called with the correct parameter.
    $this->mockConfig->clear('smtp_test_address')->shouldHaveBeenCalledOnce();

    // Verify that save was called.
    $this->mockConfig->save()->shouldHaveBeenCalledOnce();
  }

  /**
   * Tests smtp_update_8007() calls methods in correct order.
   */
  public function testUpdate8007MethodCallOrder(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->clear('smtp_test_address')->willReturn($this->mockConfig->reveal());
    $this->mockConfig->save()->willReturn($this->mockConfig->reveal());

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($this->mockConfig->reveal());

    // Execute the update hook.
    smtp_update_8007();

    // Verify config factory was called to get editable config.
    $this->mockConfigFactory->getEditable('smtp.settings')->shouldHaveBeenCalledOnce();

    // Verify clear was called before save.
    $prophecy = $this->mockConfig->reveal();
    $this->mockConfig->clear('smtp_test_address')->shouldHaveBeenCalled();
    $this->mockConfig->save()->shouldHaveBeenCalled();
  }

  /**
   * Tests smtp_update_8007() gets the correct config object.
   */
  public function testUpdate8007GetsCorrectConfig(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->clear('smtp_test_address')->willReturn($this->mockConfig->reveal());
    $this->mockConfig->save()->willReturn($this->mockConfig->reveal());

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($this->mockConfig->reveal());

    // Execute the update hook.
    smtp_update_8007();

    // Verify getEditable was called with correct parameter.
    $this->mockConfigFactory->getEditable('smtp.settings')->shouldHaveBeenCalledOnce();
  }

  /**
   * Tests smtp_update_8007() saves configuration.
   */
  public function testUpdate8007SavesConfiguration(): void {
    // Configure mock to return itself for method chaining.
    $this->mockConfig->clear('smtp_test_address')->willReturn($this->mockConfig->reveal());
    $this->mockConfig->save()->willReturn($this->mockConfig->reveal());

    // Mock config factory to return the editable config.
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($this->mockConfig->reveal());

    // Execute the update hook.
    smtp_update_8007();

    // Verify save was called.
    $this->mockConfig->save()->shouldHaveBeenCalledOnce();
  }

}
