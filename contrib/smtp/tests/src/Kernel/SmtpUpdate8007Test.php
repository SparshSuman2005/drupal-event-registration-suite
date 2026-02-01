<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for smtp_update_8007() function.
 *
 * @group smtp
 */
class SmtpUpdate8007Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['smtp', 'system'];

  /**
   * Disable strict config schema checking for this test.
   *
   * The smtp_test_address config key is being removed by the update hook
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
    if (!function_exists('smtp_update_8007')) {
      require_once dirname(__DIR__, 3) . '/smtp.install';
    }

    // Set up legacy config with smtp_test_address.
    $config = $this->config('smtp.settings');
    $config->setData($this->getLegacyConfig());
    $config->save();
  }

  /**
   * Tests smtp_update_8007() removes smtp_test_address from configuration.
   */
  public function testUpdate8007RemovesSmtpTestAddress(): void {

    // Run the update hook.
    smtp_update_8007();

    // Clear static cache to get fresh data.
    \Drupal::configFactory()->clearStaticCache();

    // Verify config migration.
    $settings_after    = $this->config('smtp.settings')->getRawData();
    $expected_settings = $this->getExpectedSettings();
    $this->assertEquals($expected_settings, $settings_after);

    // Verify smtp_test_address was removed.
    $this->assertArrayNotHasKey('smtp_test_address', $settings_after);
  }

  /**
   * Gets the legacy config with smtp_test_address.
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
      'smtp_debug_level'     => 0,
      'prev_mail_system'     => '',
      'smtp_keepalive'       => FALSE,
      'smtp_test_address'    => 'test@example.com',
    ];
  }

  /**
   * Gets the expected settings after the update (without smtp_test_address).
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
      'smtp_debug_level'     => 0,
      'prev_mail_system'     => '',
      'smtp_keepalive'       => FALSE,
    ];
  }

}
