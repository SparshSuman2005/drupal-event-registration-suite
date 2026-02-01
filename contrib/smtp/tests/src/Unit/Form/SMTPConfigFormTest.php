<?php

namespace Drupal\Tests\smtp\Unit\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Form\SMTPConfigForm;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Validate requirements for SMTPConfigForm.
 *
 * @group SMTP
 */
class SMTPConfigFormTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The mock config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactory;

  /**
   * The mock config (read-only).
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfig;

  /**
   * The mock editable config.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockEditableConfig;

  /**
   * The mock config system site.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigSystemSite;

  /**
   * The mock typed config manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockTypedConfigManager;

  /**
   * The mock messenger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockMessenger;

  /**
   * The mock email validator.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockEmailValidator;

  /**
   * The mock current user.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockCurrentUser;

  /**
   * The mock mail manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockMailManager;

  /**
   * The mock module handler.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockModuleHandler;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Test setup.
   */
  public function setup(): void {
    parent::setup();

    $this->container = new ContainerBuilder();

    $this->mockConfigFactory = $this->prophesize(ConfigFactoryInterface::class);
    // Use ImmutableConfig for read-only config.
    $this->mockConfig = $this->prophesize(ImmutableConfig::class);
    $this->mockConfigFactory->get('smtp.settings')->willReturn($this->mockConfig->reveal());
    // Use Config for editable config.
    $this->mockEditableConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($this->mockEditableConfig->reveal());

    $this->mockConfigSystemSite = $this->prophesize(ImmutableConfig::class);
    $this->mockConfigSystemSite->get('name')->willReturn('Site name');
    $this->mockConfigFactory->get('system.site')->willReturn($this->mockConfigSystemSite->reveal());

    $this->mockTypedConfigManager = $this->prophesize(TypedConfigManagerInterface::class);
    $this->mockMessenger = $this->prophesize(MessengerInterface::class);
    $this->mockEmailValidator = $this->prophesize(EmailValidatorInterface::class);
    $this->mockCurrentUser = $this->prophesize(AccountProxyInterface::class);
    $this->mockMailManager = $this->prophesize(MailManagerInterface::class);
    $this->mockModuleHandler = $this->prophesize(ModuleHandlerInterface::class);

    $this->container->set('config.factory', $this->mockConfigFactory->reveal());
    $this->container->set('config.typed', $this->mockTypedConfigManager->reveal());
    $this->container->set('messenger', $this->mockMessenger->reveal());
    $this->container->set('email.validator', $this->mockEmailValidator->reveal());
    $this->container->set('current_user', $this->mockCurrentUser->reveal());
    $this->container->set('plugin.manager.mail', $this->mockMailManager->reveal());
    $this->container->set('module_handler', $this->mockModuleHandler->reveal());

    $this->container->set('string_translation', $this->getStringTranslationStub());

    \Drupal::setContainer($this->container);
  }

  /**
   * Sets the default smtp config.
   */
  protected function setDefaultConfig() {
    // Allow any get() calls with default empty return.
    $this->mockConfig->get(Argument::any())->willReturn('');

    // Override specific values.
    $this->mockConfig->get('smtp_on')->willReturn(TRUE);
    $this->mockConfig->get('smtp_autotls')->willReturn(TRUE);
    $this->mockConfig->get('smtp_allowhtml')->willReturn(FALSE);
    $this->mockConfig->get('smtp_debug_level')->willReturn(1);
    $this->mockConfig->get('smtp_keepalive')->willReturn(FALSE);
    $this->mockConfig->get('log_level')->willReturn(3);
    // Mock hasOverrides() to return FALSE by default (no overrides).
    $this->mockConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    // Set up editable config.
    $this->mockEditableConfig->get(Argument::any())->willReturn('');
    $this->mockEditableConfig->get('smtp_on')->willReturn(TRUE);
    $this->mockEditableConfig->get('smtp_autotls')->willReturn(TRUE);
    $this->mockEditableConfig->get('smtp_allowhtml')->willReturn(FALSE);
    $this->mockEditableConfig->get('smtp_debug_level')->willReturn(1);
    $this->mockEditableConfig->get('smtp_keepalive')->willReturn(FALSE);
    $this->mockEditableConfig->get('log_level')->willReturn(3);
  }

  /**
   * Sets up basic form values for validation tests.
   *
   * @param array $overrides
   *   Array of values to override the defaults.
   *
   * @return array
   *   Array of form values.
   */
  protected function getBasicFormValues(array $overrides = []) {
    $defaults = [
      'smtp_on' => 'on',
      'smtp_host' => 'smtp.example.com',
      'smtp_hostbackup' => '',
      'smtp_port' => '587',
      'smtp_protocol' => 'standard',
      'smtp_autotls' => 'off',
      'smtp_timeout' => '30',
      'smtp_username' => '',
      'smtp_password' => '',
      'smtp_from' => '',
      'smtp_fromname' => '',
      'smtp_client_hostname' => '',
      'smtp_client_helo' => '',
      'smtp_allowhtml' => FALSE,
      'smtp_test_address' => '',
      'smtp_reroute_address' => '',
      'smtp_debug_level' => 1,
      'smtp_keepalive' => FALSE,
      'log_level' => 3,
    ];

    return array_merge($defaults, $overrides);
  }

  /**
   * Creates a fresh FormState for validation tests.
   *
   * @return \Drupal\Core\Form\FormState
   *   A new FormState instance.
   */
  public function createFormState() {
    return new FormState();
  }

  /**
   * Test if enabled message is properly shown.
   */
  public function testBuildFormEnabledMessage() {
    $this->setDefaultConfig();
    $this->mockConfig->get('smtp_on')->willReturn(TRUE);

    $formBuilder = SMTPConfigForm::create($this->container);

    $form = [];
    $formBuilder->buildForm($form, new FormState());
    $this->mockMessenger->addMessage(Argument::which('getUntranslatedString', 'SMTP module is active.'))->shouldHaveBeenCalled();
  }

  /**
   * Test if enabled message is properly shown.
   */
  public function testBuildFormDisabledMessage() {
    $this->setDefaultConfig();
    $this->mockConfig->get('smtp_on')->willReturn(FALSE);

    $formBuilder = SMTPConfigForm::create($this->container);

    $form = [];
    $formBuilder->buildForm($form, new FormState());
    $this->mockMessenger->addMessage(Argument::which('getUntranslatedString', 'SMTP module is INACTIVE.'))->shouldHaveBeenCalled();
  }

  /**
   * Test form id.
   */
  public function testGetFormId() {
    $formBuilder = SMTPConfigForm::create($this->container);

    $form_id = $formBuilder->getFormId();
    $this->assertEquals('smtp_admin_settings', $form_id);
  }

  /**
   * Test get editable config names.
   */
  public function testGetEditableConfigNames() {
    $form = SMTPConfigForm::create($this->container);
    // Make method public with Reflection.
    $method = new \ReflectionMethod($form, 'getEditableConfigNames');
    $method->setAccessible(TRUE);
    $editable_config_names = $method->invoke($form);
    $this->assertEquals(['smtp.settings'], $editable_config_names);
  }

  /**
   * Test validateForm with valid data.
   */
  public function testValidateFormValidData() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_from' => 'test@example.com',
      'smtp_test_address' => 'test@example.com',
      'smtp_reroute_address' => 'reroute@example.com',
      'smtp_username' => 'user@example.com',
      'smtp_password' => 'password123',
    ]));

    $this->mockEmailValidator->isValid('test@example.com')->willReturn(TRUE);
    $this->mockEmailValidator->isValid('test@example.com')->willReturn(TRUE);
    $this->mockEmailValidator->isValid('reroute@example.com')->willReturn(TRUE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Check that no errors are set.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test validateForm with missing SMTP host when SMTP is enabled.
   */
  public function testValidateFormMissingHostWhenEnabled() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_host' => '']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_host', $form_state->getErrors());
  }

  /**
   * Test validateForm with missing SMTP port when SMTP is enabled.
   */
  public function testValidateFormMissingPortWhenEnabled() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_port' => '']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_port', $form_state->getErrors());
  }

  /**
   * Test validateForm with empty timeout.
   */
  public function testValidateFormEmptyTimeout() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_timeout' => '']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_timeout', $form_state->getErrors());
  }

  /**
   * Test validateForm with timeout less than 1.
   */
  public function testValidateFormTimeoutLessThanOne() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_timeout' => '0']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_timeout', $form_state->getErrors());
  }

  /**
   * Test validateForm with invalid from email address.
   */
  public function testValidateFormInvalidFromEmail() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_from' => 'invalid-email']));

    $this->mockEmailValidator->isValid('invalid-email')->willReturn(FALSE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_from', $form_state->getErrors());
  }

  /**
   * Test validateForm with invalid test email address.
   *
   * Note: Test email functionality has been moved to TestForm.
   * This test is removed as smtp_test_address is no longer validated
   * in SMTPConfigForm.
   */
  public function testValidateFormInvalidTestEmail() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_test_address' => 'invalid-test-email']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Test email validation is no longer part of SMTPConfigForm.
    // Since validation doesn't occur for smtp_test_address,
    // there should be no errors.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test validateForm with invalid reroute email address.
   */
  public function testValidateFormInvalidRerouteEmail() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_reroute_address' => 'invalid-reroute-email']));

    $this->mockEmailValidator->isValid('invalid-reroute-email')->willReturn(FALSE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_reroute_address', $form_state->getErrors());
  }

  /**
   * Test validateForm password handling when username is empty.
   */
  public function testValidateFormPasswordHandlingEmptyUsername() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_username' => '', 'smtp_password' => 'some-password']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Password should be cleared when username is empty.
    // Note: The validation method modifies the local $values array but doesn't
    // update form state.
    // This test verifies the validation logic runs without errors.
    // Check that no errors are set.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test validateForm password when password is empty but username exists.
   */
  public function testValidateFormPasswordHandlingEmptyPassword() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_username' => 'user@example.com', 'smtp_password' => '']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Password should be unset when empty (to preserve existing password).
    $values = $form_state->getValues();
    $this->assertArrayNotHasKey('smtp_password', $values);
  }

  /**
   * Test validateForm with SMTP disabled - should not validate host/port.
   */
  public function testValidateFormSmtpDisabled() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues(['smtp_on' => 'off', 'smtp_host' => '', 'smtp_port' => '']));

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Should not have errors for missing host/port when SMTP is disabled.
    // Check that no errors are set.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test validateForm with valid email addresses.
   */
  public function testValidateFormValidEmailAddresses() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_from' => 'from@example.com',
      'smtp_test_address' => 'test@example.com',
      'smtp_reroute_address' => 'reroute@example.com',
    ]));

    $this->mockEmailValidator->isValid('from@example.com')->willReturn(TRUE);
    $this->mockEmailValidator->isValid('test@example.com')->willReturn(TRUE);
    $this->mockEmailValidator->isValid('reroute@example.com')->willReturn(TRUE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Check that no errors are set.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test validateForm with empty email addresses (should not validate).
   */
  public function testValidateFormEmptyEmailAddresses() {
    $this->setDefaultConfig();
    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues());

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Empty email addresses should not cause validation errors.
    // Check that no errors are set.
    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Expected no validation errors, but got: ' . print_r($errors, TRUE));
  }

  /**
   * Test submitForm with valid data and SMTP enabled.
   */
  public function testSubmitFormSmtpEnabled() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system.
    $mockMailConfig->get('interface.default')->willReturn('php_mail');
    $mockMailConfig->get('interface')->willReturn(['default' => 'php_mail']);

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(TRUE);
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'on',
      'smtp_host' => 'smtp.example.com',
      'smtp_port' => '587',
      'smtp_username' => 'user@example.com',
      'smtp_password' => 'password123',
      'smtp_from' => 'from@example.com',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify config was saved.
    $mockEditableConfig->set('smtp_on', TRUE)->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_host', 'smtp.example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_port', '587')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_username', 'user@example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_password', 'password123')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_from', 'from@example.com')->shouldHaveBeenCalled();

    // Verify mail system was switched.
    $mockMailConfig->set('interface.default', 'SMTPMailSystem')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm with SMTP disabled.
   */
  public function testSubmitFormSmtpDisabled() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system.
    $mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');

    // Mock config saving.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(FALSE);
    $mockEditableConfig->get('prev_mail_system')->willReturn('php_mail');
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'off',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify SMTP was disabled.
    $mockEditableConfig->set('smtp_on', FALSE)->shouldHaveBeenCalled();

    // Verify mail system was reverted.
    $mockMailConfig->set('interface.default', 'php_mail')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm with test email sending.
   *
   * Note: Test email functionality has been moved to TestForm.
   * This test verifies that SMTPConfigForm does not send test emails.
   */
  public function testSubmitFormWithTestEmail() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system.
    $mockMailConfig->get('interface.default')->willReturn('php_mail');
    $mockMailConfig->get('interface')->willReturn(['default' => 'php_mail']);

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(TRUE);
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'on',
      'smtp_test_address' => 'test@example.com',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify test email was NOT sent (functionality moved to TestForm).
    $this->mockMailManager->mail('smtp', 'smtp-test', 'test@example.com', 'en', Argument::any())->shouldNotHaveBeenCalled();

    // Verify mail system was switched.
    $mockMailConfig->set('interface.default', 'SMTPMailSystem')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm with test email when SMTP is disabled.
   *
   * Note: Test email functionality has been moved to TestForm.
   * This test verifies that SMTPConfigForm does not send test emails.
   */
  public function testSubmitFormWithTestEmailSmtpDisabled() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system - allow any get() calls.
    $mockMailConfig->get(Argument::any())->willReturn('');
    $mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(FALSE);
    $mockEditableConfig->get('prev_mail_system')->willReturn('php_mail');
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'off',
      'smtp_test_address' => 'test@example.com',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify test email was NOT sent (functionality moved to TestForm).
    $this->mockMailManager->mail('smtp', 'smtp-test', 'test@example.com', 'en', Argument::any())->shouldNotHaveBeenCalled();

    // Verify mail system was reverted to php_mail.
    $mockMailConfig->set('interface.default', 'php_mail')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm with overridden settings.
   */
  public function testSubmitFormWithOverriddenSettings() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system.
    $mockMailConfig->get('interface.default')->willReturn('php_mail');

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(TRUE);

    // Mock hasOverrides on the READ-ONLY config (this is what the form checks).
    $this->mockConfig->hasOverrides('smtp_password')->willReturn(TRUE);
    $this->mockConfig->hasOverrides('smtp_on')->willReturn(FALSE);
    $this->mockConfig->hasOverrides('smtp_autotls')->willReturn(FALSE);
    $this->mockConfig->hasOverrides('smtp_host')->willReturn(FALSE);
    $this->mockConfig->hasOverrides('smtp_port')->willReturn(FALSE);
    $this->mockConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'on',
      'smtp_password' => 'should-not-be-saved',
      'smtp_host' => 'smtp.example.com',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify password was NOT saved (overridden)
    $mockEditableConfig->set('smtp_password', 'should-not-be-saved')->shouldNotHaveBeenCalled();

    // Verify other settings were saved.
    $mockEditableConfig->set('smtp_on', TRUE)->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_host', 'smtp.example.com')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm with all config keys.
   */
  public function testSubmitFormAllConfigKeys() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system.
    $mockMailConfig->get('interface.default')->willReturn('php_mail');

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(TRUE);
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'on',
      'smtp_autotls' => 'on',
      'smtp_host' => 'smtp.example.com',
      'smtp_hostbackup' => 'backup.example.com',
      'smtp_port' => '587',
      'smtp_protocol' => 'tls',
      'smtp_timeout' => '30',
      'smtp_username' => 'user@example.com',
      'smtp_password' => 'password123',
      'smtp_from' => 'from@example.com',
      'smtp_fromname' => 'Test Site',
      'smtp_client_hostname' => 'client.example.com',
      'smtp_client_helo' => 'helo.example.com',
      'smtp_allowhtml' => TRUE,
      'smtp_debug_level' => 2,
      'smtp_keepalive' => TRUE,
      'smtp_reroute_address' => 'reroute@example.com',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify all config keys were saved.
    $mockEditableConfig->set('smtp_on', TRUE)->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_host', 'smtp.example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_hostbackup', 'backup.example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_port', '587')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_protocol', 'tls')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_timeout', '30')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_username', 'user@example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_password', 'password123')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_from', 'from@example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_fromname', 'Test Site')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_client_hostname', 'client.example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_client_helo', 'helo.example.com')->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_allowhtml', TRUE)->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_keepalive', TRUE)->shouldHaveBeenCalled();
    $mockEditableConfig->set('smtp_reroute_address', 'reroute@example.com')->shouldHaveBeenCalled();
  }

  /**
   * Test submitForm mail system switching when already SMTPMailSystem.
   */
  public function testSubmitFormMailSystemAlreadySmtp() {
    $this->setDefaultConfig();

    // Mock editable configs.
    $mockEditableConfig = $this->prophesize(Config::class);
    $mockMailConfig = $this->prophesize(Config::class);
    $this->mockConfigFactory->getEditable('smtp.settings')->willReturn($mockEditableConfig->reveal());
    $this->mockConfigFactory->getEditable('system.mail')->willReturn($mockMailConfig->reveal());

    // Mock current mail system is already SMTPMailSystem.
    $mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');

    // Mock config saving - allow any get() calls.
    $mockEditableConfig->set(Argument::any(), Argument::any())->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->save()->willReturn($mockEditableConfig->reveal());
    $mockEditableConfig->get(Argument::any())->willReturn('');
    $mockEditableConfig->get('smtp_on')->willReturn(TRUE);
    $mockEditableConfig->hasOverrides(Argument::any())->willReturn(FALSE);

    $mockMailConfig->set(Argument::any(), Argument::any())->willReturn($mockMailConfig->reveal());
    $mockMailConfig->save()->willReturn($mockMailConfig->reveal());

    $form = SMTPConfigForm::create($this->container);

    $form_state = $this->createFormState();
    $form_state->setValues($this->getBasicFormValues([
      'smtp_on' => 'on',
    ]));

    $form_array = [];
    $form->submitForm($form_array, $form_state);

    // Verify prev_mail_system was NOT set (already SMTPMailSystem)
    $mockMailConfig->set('prev_mail_system', Argument::any())->shouldNotHaveBeenCalled();

    // Verify interface was NOT changed since it's already SMTPMailSystem.
    $mockMailConfig->set('interface.default', 'SMTPMailSystem')->shouldNotHaveBeenCalled();
  }

}
