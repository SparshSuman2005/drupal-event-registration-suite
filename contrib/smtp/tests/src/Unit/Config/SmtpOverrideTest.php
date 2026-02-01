<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit\Config;

use Drupal\Core\Config\StorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Config\SmtpOverride;

/**
 * Unit tests for SmtpOverride.
 *
 * @group smtp
 * @coversDefaultClass \Drupal\smtp\Config\SmtpOverride
 */
class SmtpOverrideTest extends UnitTestCase {

  /**
   * The SmtpOverride service.
   *
   * @var \Drupal\smtp\Config\SmtpOverride
   */
  protected $smtpOverride;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->smtpOverride = new SmtpOverride();
  }

  /**
   * Tests loadOverrides with system.mail config.
   *
   * @covers ::loadOverrides
   */
  public function testLoadOverridesWithSystemMail(): void {
    $names = ['system.mail'];
    $overrides = $this->smtpOverride->loadOverrides($names);

    $this->assertIsArray($overrides);
    $this->assertArrayHasKey('system.mail', $overrides);
    $this->assertArrayHasKey('interface', $overrides['system.mail']);
    $this->assertArrayHasKey('default', $overrides['system.mail']['interface']);
    $this->assertEquals('SMTPMailSystem', $overrides['system.mail']['interface']['default']);
  }

  /**
   * Tests loadOverrides with multiple config names including system.mail.
   *
   * @covers ::loadOverrides
   */
  public function testLoadOverridesWithMultipleConfigs(): void {
    $names = ['user.settings', 'system.mail', 'node.settings'];
    $overrides = $this->smtpOverride->loadOverrides($names);

    $this->assertIsArray($overrides);
    $this->assertArrayHasKey('system.mail', $overrides);
    $this->assertArrayNotHasKey('user.settings', $overrides);
    $this->assertArrayNotHasKey('node.settings', $overrides);
    $this->assertEquals('SMTPMailSystem', $overrides['system.mail']['interface']['default']);
  }

  /**
   * Tests loadOverrides without system.mail config.
   *
   * @covers ::loadOverrides
   */
  public function testLoadOverridesWithoutSystemMail(): void {
    $names = ['user.settings', 'node.settings'];
    $overrides = $this->smtpOverride->loadOverrides($names);

    $this->assertIsArray($overrides);
    $this->assertEmpty($overrides);
    $this->assertArrayNotHasKey('system.mail', $overrides);
  }

  /**
   * Tests loadOverrides with empty names array.
   *
   * @covers ::loadOverrides
   */
  public function testLoadOverridesWithEmptyNames(): void {
    $names = [];
    $overrides = $this->smtpOverride->loadOverrides($names);

    $this->assertIsArray($overrides);
    $this->assertEmpty($overrides);
  }

  /**
   * Tests getCacheSuffix method.
   *
   * @covers ::getCacheSuffix
   */
  public function testGetCacheSuffix(): void {
    $suffix = $this->smtpOverride->getCacheSuffix();
    $this->assertEquals('SmtpOverride', $suffix);
  }

  /**
   * Tests getCacheableMetadata method.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata(): void {
    $metadata = $this->smtpOverride->getCacheableMetadata('system.mail');
    $this->assertNull($metadata);

    $metadata = $this->smtpOverride->getCacheableMetadata('user.settings');
    $this->assertNull($metadata);
  }

  /**
   * Tests createConfigObject method.
   *
   * @covers ::createConfigObject
   */
  public function testCreateConfigObject(): void {
    $configObject = $this->smtpOverride->createConfigObject('system.mail');
    $this->assertNull($configObject);

    $configObject = $this->smtpOverride->createConfigObject('system.mail', StorageInterface::DEFAULT_COLLECTION);
    $this->assertNull($configObject);
  }

  /**
   * Tests override structure integrity.
   *
   * @covers ::loadOverrides
   */
  public function testOverrideStructureIntegrity(): void {
    $names = ['system.mail'];
    $overrides = $this->smtpOverride->loadOverrides($names);

    // Verify the complete structure of the override.
    $expected = [
      'system.mail' => [
        'interface' => [
          'default' => 'SMTPMailSystem',
        ],
      ],
    ];

    $this->assertEquals($expected, $overrides);
  }

}
