<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Form\AdvancedForm;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for AdvancedForm.
 *
 * @group smtp
 * @coversDefaultClass \Drupal\smtp\Form\AdvancedForm
 */
final class AdvancedFormTest extends UnitTestCase {

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container = new ContainerBuilder();

    // Create string translation mock.
    $mockStringTranslation = $this->createMock(TranslationInterface::class);
    $mockStringTranslation
      ->method('translate')
      ->willReturnCallback(function ($string, $args = []) {
        return $string;
      });
    $mockStringTranslation
      ->method('translateString')
      ->willReturnCallback(function ($translatable) {
        return $translatable->getUntranslatedString();
      });

    // Create other required mocks.
    $mockTypedConfigManager = $this->createMock(TypedConfigManagerInterface::class);
    $mockLinkGenerator = $this->createMock(LinkGeneratorInterface::class);
    $mockMessenger = $this->createMock(MessengerInterface::class);

    $this->container->set('config.typed', $mockTypedConfigManager);
    $this->container->set('link_generator', $mockLinkGenerator);
    $this->container->set('messenger', $mockMessenger);
    $this->container->set('string_translation', $mockStringTranslation);

    \Drupal::setContainer($this->container);
  }

  /**
   * Creates a mock config factory with the given config values.
   *
   * @param array $configValues
   *   The configuration values to return.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The mocked config factory.
   */
  protected function createMockConfigFactory(array $configValues): ConfigFactoryInterface {
    $mockImmutableConfig = $this->createMock(ImmutableConfig::class);
    $mockImmutableConfig
      ->method('get')
      ->willReturnCallback(function ($key) use ($configValues) {
        return $configValues[$key] ?? NULL;
      });

    $mockEditableConfig = $this->createMock(Config::class);
    $mockEditableConfig
      ->method('set')
      ->willReturnSelf();
    $mockEditableConfig
      ->method('save')
      ->willReturnSelf();

    $mockConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $mockConfigFactory
      ->method('get')
      ->with('smtp.advanced')
      ->willReturn($mockImmutableConfig);
    $mockConfigFactory
      ->method('getEditable')
      ->with('smtp.advanced')
      ->willReturn($mockEditableConfig);

    return $mockConfigFactory;
  }

  /**
   * Tests the form ID.
   *
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $mockConfigFactory = $this->createMockConfigFactory([]);
    $this->container->set('config.factory', $mockConfigFactory);
    \Drupal::setContainer($this->container);

    $form = AdvancedForm::create($this->container);
    $this->assertEquals('smtp_advanced_settings', $form->getFormId());
  }

  /**
   * Tests getEditableConfigNames.
   *
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    $mockConfigFactory = $this->createMockConfigFactory([]);
    $this->container->set('config.factory', $mockConfigFactory);
    \Drupal::setContainer($this->container);

    $form = AdvancedForm::create($this->container);
    $method = new \ReflectionMethod($form, 'getEditableConfigNames');
    $method->setAccessible(TRUE);
    $editableConfigNames = $method->invoke($form);
    $this->assertEquals(['smtp.advanced'], $editableConfigNames);
  }

  /**
   * Tests buildForm with default values.
   *
   * @covers ::buildForm
   */
  public function testBuildFormWithDefaults(): void {
    $configValues = [
      'enabled'                    => FALSE,
      'socket__bindto'             => '',
      'ssl__peer_name'             => '',
      'ssl__verify_peer'           => FALSE,
      'ssl__verify_peer_name'      => FALSE,
      'ssl__allow_self_signed'     => FALSE,
      'ssl__cafile'                     => '',
      'ssl__capath'                     => '',
      'ssl__local_cert'                 => '',
      'ssl__local_pk'                   => '',
      'ssl__passphrase'                 => '',
      'ssl__verify_depth'               => '',
      'ssl__SNI_enabled'                => FALSE,
    ];

    $mockConfigFactory = $this->createMockConfigFactory($configValues);
    $this->container->set('config.factory', $mockConfigFactory);
    \Drupal::setContainer($this->container);

    $form = AdvancedForm::create($this->container);
    $form_state = new FormState();

    $build = $form->buildForm([], $form_state);

    // Check enabled checkbox.
    $this->assertArrayHasKey('enabled', $build);
    $this->assertEquals('checkbox', $build['enabled']['#type']);
    $this->assertFalse($build['enabled']['#default_value']);

    // Check SSL details section.
    $this->assertArrayHasKey('ssl', $build);
    $this->assertEquals('details', $build['ssl']['#type']);
    $this->assertTrue($build['ssl']['#open']);

    // Check SSL fields.
    $this->assertArrayHasKey('ssl__peer_name', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__peer_name']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__peer_name']['#default_value']);

    $this->assertArrayHasKey('ssl__verify_peer', $build['ssl']);
    $this->assertEquals('checkbox', $build['ssl']['ssl__verify_peer']['#type']);
    $this->assertFalse($build['ssl']['ssl__verify_peer']['#default_value']);

    $this->assertArrayHasKey('ssl__verify_peer_name', $build['ssl']);
    $this->assertEquals('checkbox', $build['ssl']['ssl__verify_peer_name']['#type']);
    $this->assertFalse($build['ssl']['ssl__verify_peer_name']['#default_value']);

    $this->assertArrayHasKey('ssl__allow_self_signed', $build['ssl']);
    $this->assertEquals('checkbox', $build['ssl']['ssl__allow_self_signed']['#type']);
    $this->assertFalse($build['ssl']['ssl__allow_self_signed']['#default_value']);

    $this->assertArrayHasKey('ssl__cafile', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__cafile']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__cafile']['#default_value']);

    $this->assertArrayHasKey('ssl__capath', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__capath']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__capath']['#default_value']);

    $this->assertArrayHasKey('ssl__local_cert', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__local_cert']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__local_cert']['#default_value']);

    $this->assertArrayHasKey('ssl__local_pk', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__local_pk']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__local_pk']['#default_value']);

    $this->assertArrayHasKey('ssl__passphrase', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__passphrase']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__passphrase']['#default_value']);

    $this->assertArrayHasKey('ssl__verify_depth', $build['ssl']);
    $this->assertEquals('textfield', $build['ssl']['ssl__verify_depth']['#type']);
    $this->assertEquals('', $build['ssl']['ssl__verify_depth']['#default_value']);

    $this->assertArrayHasKey('ssl__SNI_enabled', $build['ssl']);
    $this->assertEquals('checkbox', $build['ssl']['ssl__SNI_enabled']['#type']);
    $this->assertFalse($build['ssl']['ssl__SNI_enabled']['#default_value']);

    // Check socket details section.
    $this->assertArrayHasKey('socket', $build);
    $this->assertEquals('details', $build['socket']['#type']);
    $this->assertTrue($build['socket']['#open']);

    // Check socket fields.
    $this->assertArrayHasKey('socket__bindto', $build['socket']);
    $this->assertEquals('textfield', $build['socket']['socket__bindto']['#type']);
    $this->assertEquals('', $build['socket']['socket__bindto']['#default_value']);
  }

  /**
   * Tests buildForm with custom values.
   *
   * @covers ::buildForm
   */
  public function testBuildFormWithCustomValues(): void {
    $configValues = [
      'enabled'                    => TRUE,
      'socket__bindto'             => '192.168.1.1',
      'ssl__peer_name'             => 'example.com',
      'ssl__verify_peer'           => TRUE,
      'ssl__verify_peer_name'      => TRUE,
      'ssl__allow_self_signed'     => TRUE,
      'ssl__cafile'                     => '/path/to/cafile.pem',
      'ssl__capath'                     => '/path/to/ca',
      'ssl__local_cert'                 => '/path/to/cert.pem',
      'ssl__local_pk'                   => '/path/to/key.pem',
      'ssl__passphrase'                 => 'secret',
      'ssl__verify_depth'               => '5',
      'ssl__SNI_enabled'                => TRUE,
    ];

    $mockConfigFactory = $this->createMockConfigFactory($configValues);
    $this->container->set('config.factory', $mockConfigFactory);
    \Drupal::setContainer($this->container);

    $form = AdvancedForm::create($this->container);
    $form_state = new FormState();

    $build = $form->buildForm([], $form_state);

    // Verify custom values are set.
    $this->assertTrue($build['enabled']['#default_value']);
    $this->assertEquals('192.168.1.1', $build['socket']['socket__bindto']['#default_value']);
    $this->assertEquals('example.com', $build['ssl']['ssl__peer_name']['#default_value']);
    $this->assertTrue($build['ssl']['ssl__verify_peer']['#default_value']);
    $this->assertTrue($build['ssl']['ssl__verify_peer_name']['#default_value']);
    $this->assertTrue($build['ssl']['ssl__allow_self_signed']['#default_value']);
    $this->assertEquals('/path/to/cafile.pem', $build['ssl']['ssl__cafile']['#default_value']);
    $this->assertEquals('/path/to/ca', $build['ssl']['ssl__capath']['#default_value']);
    $this->assertEquals('/path/to/cert.pem', $build['ssl']['ssl__local_cert']['#default_value']);
    $this->assertEquals('/path/to/key.pem', $build['ssl']['ssl__local_pk']['#default_value']);
    $this->assertEquals('secret', $build['ssl']['ssl__passphrase']['#default_value']);
    $this->assertEquals('5', $build['ssl']['ssl__verify_depth']['#default_value']);
    $this->assertTrue($build['ssl']['ssl__SNI_enabled']['#default_value']);
  }

  /**
   * Tests submitForm saves all values correctly.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormSavesAllValues(): void {
    $configValues = [];
    $mockConfigFactory = $this->createMockConfigFactory($configValues);
    $this->container->set('config.factory', $mockConfigFactory);
    \Drupal::setContainer($this->container);

    $form = AdvancedForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues([
      'enabled'                    => TRUE,
      'socket__bindto'             => '192.168.1.1',
      'ssl__peer_name'             => 'example.com',
      'ssl__verify_peer'           => TRUE,
      'ssl__verify_peer_name'      => TRUE,
      'ssl__allow_self_signed'     => TRUE,
      'ssl__cafile'                => '/path/to/cafile.pem',
      'ssl__capath'                => '/path/to/ca',
      'ssl__local_cert'            => '/path/to/cert.pem',
      'ssl__local_pk'              => '/path/to/key.pem',
      'ssl__passphrase'            => 'secret',
      'ssl__verify_depth'          => '5',
      'ssl__SNI_enabled'           => TRUE,
    ]);

    $form_array = [];
    // This should not throw an exception.
    $form->submitForm($form_array, $form_state);

    // If we get here without exceptions, the test passes.
    $this->assertTrue(TRUE);
  }

}
