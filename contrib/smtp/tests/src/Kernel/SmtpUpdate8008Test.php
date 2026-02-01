<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for smtp_update_8008() function.
 *
 * @group smtp
 */
class SmtpUpdate8008Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['smtp', 'system'];

  /**
   * Disable strict config schema checking for this test.
   *
   * The smtp_debugging config key is being removed by the update hook
   * and no longer exists in the schema, so we need to disable strict checking
   * to test its removal.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['smtp']);

    // Include the install file if function doesn't exist.
    if (!function_exists('smtp_update_8008')) {
      require_once dirname(__DIR__, 3) . '/smtp.install';
    }

    // Set up legacy config without log_level.
    $config = $this->config('smtp.settings');
    $config->setData($this->getLegacyConfig());
    $config->save();
  }

  /**
   * Tests smtp_update_8008() set log_level to ERROR if smtp_debugging is false.
   */
  public function testUpdate8008SetsLogLevelWhenDebuggingFalse(): void {
    // Verify log_level is not set before update.
    $settings_before = $this->config('smtp.settings')->getRawData();
    $this->assertArrayNotHasKey('log_level', $settings_before);
    $this->assertArrayHasKey('smtp_debugging', $settings_before);
    $this->assertFalse($settings_before['smtp_debugging']);

    // Run the update hook.
    smtp_update_8008();

    // Clear static cache to get fresh data.
    \Drupal::configFactory()->clearStaticCache();

    // Verify log_level is now set to ERROR.
    $settings_after = $this->config('smtp.settings')->getRawData();
    $this->assertArrayHasKey('log_level', $settings_after);
    $this->assertEquals(RfcLogLevel::ERROR, $settings_after['log_level']);

    // Verify smtp_debugging is removed.
    $this->assertArrayNotHasKey('smtp_debugging', $settings_after);

    // Verify other settings remain unchanged.
    $expected_settings = $this->getExpectedSettings();
    $this->assertEquals($expected_settings, $settings_after);
  }

  /**
   * Tests smtp_update_8008() migrates smtp_debugging to log_level DEBUG.
   */
  public function testUpdate8008MigratesDebuggingToLogLevel(): void {
    // Set up config with smtp_debugging enabled.
    $config = $this->config('smtp.settings');
    $legacy_config = $this->getLegacyConfig();
    $legacy_config['smtp_debugging'] = TRUE;
    $config->setData($legacy_config);
    $config->save();

    // Verify smtp_debugging is set and log_level is not.
    $settings_before = $this->config('smtp.settings')->getRawData();
    $this->assertTrue($settings_before['smtp_debugging']);
    $this->assertArrayNotHasKey('log_level', $settings_before);

    // Run the update hook.
    smtp_update_8008();

    // Clear static cache to get fresh data.
    \Drupal::configFactory()->clearStaticCache();

    // Verify log_level is now set to DEBUG.
    $settings_after = $this->config('smtp.settings')->getRawData();
    $this->assertArrayHasKey('log_level', $settings_after);
    $this->assertEquals(RfcLogLevel::DEBUG, $settings_after['log_level']);

    // Verify smtp_debugging is removed.
    $this->assertArrayNotHasKey('smtp_debugging', $settings_after);
  }

  /**
   * Gets the legacy config without log_level.
   *
   * @return array
   *   The legacy configuration array.
   */
  protected function getLegacyConfig(): array {
    return [
      'smtp_on'              => FALSE,
      'smtp_host'            => '',
      'smtp_hostbackup'      => '',
      'smtp_port'            => '25',
      'smtp_protocol'        => 'standard',
      'smtp_autotls'         => TRUE,
      'smtp_timeout'         => 30,
      'smtp_username'        => '',
      'smtp_password'        => '',
      'smtp_from'            => '',
      'smtp_fromname'        => '',
      'smtp_client_hostname' => '',
      'smtp_client_helo'     => '',
      'smtp_allowhtml'       => FALSE,
      'smtp_reroute_address' => '',
      'smtp_debugging'       => FALSE,
      'smtp_debug_level'     => 1,
      'prev_mail_system'     => 'php_mail',
      'smtp_keepalive'       => FALSE,
    ];
  }

  /**
   * Gets the expected settings after the update (with log_level).
   *
   * @return array
   *   The expected configuration array.
   */
  protected function getExpectedSettings(): array {
    return [
      'smtp_on'              => FALSE,
      'smtp_host'            => '',
      'smtp_hostbackup'      => '',
      'smtp_port'            => '25',
      'smtp_protocol'        => 'standard',
      'smtp_autotls'         => TRUE,
      'smtp_timeout'         => 30,
      'smtp_username'        => '',
      'smtp_password'        => '',
      'smtp_from'            => '',
      'smtp_fromname'        => '',
      'smtp_client_hostname' => '',
      'smtp_client_helo'     => '',
      'smtp_allowhtml'       => FALSE,
      'smtp_reroute_address' => '',
      'smtp_debug_level'     => 1,
      'prev_mail_system'     => 'php_mail',
      'smtp_keepalive'       => FALSE,
      'log_level'            => RfcLogLevel::ERROR,
    ];
  }

}
