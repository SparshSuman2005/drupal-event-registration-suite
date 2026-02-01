<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormState;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Form\TestForm;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for TestForm.
 *
 * @group smtp
 * @coversDefaultClass \Drupal\smtp\Form\TestForm
 */
class TestFormTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The mock config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactory;

  /**
   * The mock SMTP settings.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockSettings;

  /**
   * The mock system.mail config.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockMailConfig;

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
   * The mock container.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockContainer;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->mockConfigFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->mockSettings = $this->prophesize(ImmutableConfig::class);
    $this->mockConfigFactory->get('smtp.settings')->willReturn($this->mockSettings->reveal());

    $this->mockMailConfig = $this->prophesize(ImmutableConfig::class);
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $this->mockMessenger = $this->prophesize(MessengerInterface::class);
    $this->mockEmailValidator = $this->prophesize(EmailValidatorInterface::class);
    $this->mockCurrentUser = $this->prophesize(AccountProxyInterface::class);
    $this->mockMailManager = $this->prophesize(MailManagerInterface::class);

    $this->mockContainer = $this->prophesize(ContainerInterface::class);
    $this->mockContainer->get('config.factory')->willReturn($this->mockConfigFactory->reveal());
    $this->mockContainer->get('messenger')->willReturn($this->mockMessenger->reveal());
    $this->mockContainer->get('email.validator')->willReturn($this->mockEmailValidator->reveal());
    $this->mockContainer->get('current_user')->willReturn($this->mockCurrentUser->reveal());
    $this->mockContainer->get('plugin.manager.mail')->willReturn($this->mockMailManager->reveal());

    $mockStringTranslation = $this->prophesize(TranslationInterface::class);
    $mockStringTranslation->translate(Argument::any())->willReturnArgument(0);
    $mockStringTranslation->translate(Argument::any(), Argument::any())->willReturnArgument(0);
    $mockStringTranslation->translateString(Argument::any())->willReturn('.');
    $this->mockContainer->get('string_translation')->willReturn($mockStringTranslation->reveal());

    \Drupal::setContainer($this->mockContainer->reveal());
  }

  /**
   * Tests the form ID.
   *
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $form = TestForm::create($this->mockContainer->reveal());
    $this->assertEquals('smtp_test_form', $form->getFormId());
  }

  /**
   * Tests buildForm with no reroute address.
   *
   * @covers ::buildForm
   */
  public function testBuildFormNoRerouteAddress(): void {
    $this->mockSettings->get('smtp_reroute_address')->willReturn('');
    $this->mockCurrentUser->getEmail()->willReturn('user@example.com');

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();

    $build = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('smtp_test_address', $build);
    $this->assertEquals('textfield', $build['smtp_test_address']['#type']);
    $this->assertEquals('user@example.com', $build['smtp_test_address']['#default_value']);
    $this->assertFalse($build['smtp_test_address']['#disabled']);
    $this->assertTrue($build['smtp_test_address']['#required']);

    $this->assertArrayHasKey('actions', $build);
    $this->assertArrayHasKey('submit', $build['actions']);
  }

  /**
   * Tests buildForm with reroute address.
   *
   * @covers ::buildForm
   */
  public function testBuildFormWithRerouteAddress(): void {
    $this->mockSettings->get('smtp_reroute_address')->willReturn('reroute@example.com');
    $this->mockMessenger->addWarning(Argument::any())->shouldBeCalled();

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();

    $build = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('smtp_test_address', $build);
    $this->assertEquals('reroute@example.com', $build['smtp_test_address']['#default_value']);
    $this->assertTrue($build['smtp_test_address']['#disabled']);
  }

  /**
   * Tests validateForm with valid email.
   *
   * @covers ::validateForm
   */
  public function testValidateFormValidEmail(): void {
    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => 'test@example.com']);

    $this->mockEmailValidator->isValid('test@example.com')->willReturn(TRUE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertEmpty($errors);
  }

  /**
   * Tests validateForm with invalid email.
   *
   * @covers ::validateForm
   */
  public function testValidateFormInvalidEmail(): void {
    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => 'invalid-email']);

    $this->mockEmailValidator->isValid('invalid-email')->willReturn(FALSE);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertArrayHasKey('smtp_test_address', $form_state->getErrors());
  }

  /**
   * Tests validateForm with empty email.
   *
   * @covers ::validateForm
   */
  public function testValidateFormEmptyEmail(): void {
    // Empty email is invalid.
    $this->mockEmailValidator->isValid('')->willReturn(FALSE);

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => '']);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    // Empty email should cause validation errors.
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey('smtp_test_address', $errors);
  }

  /**
   * Tests submitForm when SMTP is enabled.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormSmtpEnabled(): void {
    $this->mockSettings->get('smtp_on')->willReturn(TRUE);
    $this->mockCurrentUser->getPreferredLangcode()->willReturn('en');

    // Mock mail config - SMTP is already the default.
    $this->mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');

    $this->mockMailManager->mail('smtp', 'smtp-test', 'test@example.com', 'en', Argument::any())
      ->willReturn(TRUE);
    $this->mockMessenger->addMessage(Argument::any())->shouldBeCalled();

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => 'test@example.com']);

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * Tests submitForm when SMTP is disabled.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormSmtpDisabled(): void {
    $this->mockSettings->get('smtp_on')->willReturn(FALSE);
    $this->mockCurrentUser->getPreferredLangcode()->willReturn('en');

    // Mock mail config - SMTP is not the default.
    $this->mockMailConfig->get('interface.default')->willReturn('php_mail');

    // Expect config factory to accept an override.
    $this->mockConfigFactory->addOverride(Argument::any())->shouldBeCalled();

    $this->mockMailManager->mail('smtp', 'smtp-test', 'test@example.com', 'en', Argument::any())
      ->willReturn(TRUE);
    $this->mockMessenger->addMessage(Argument::any())->shouldBeCalled();
    $this->mockMessenger->addStatus(Argument::any())->shouldBeCalled();

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => 'test@example.com']);

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

  /**
   * Tests submitForm with failed email sending.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormEmailFailed(): void {
    $this->mockSettings->get('smtp_on')->willReturn(TRUE);
    $this->mockCurrentUser->getPreferredLangcode()->willReturn('en');

    // Mock mail config - SMTP is already enabled.
    $this->mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');

    // Mail sending fails.
    $this->mockMailManager->mail('smtp', 'smtp-test', 'test@example.com', 'en', Argument::any())
      ->willReturn(FALSE);

    // Success message should not be added.
    $this->mockMessenger->addMessage(Argument::any())->shouldNotBeCalled();

    $form = TestForm::create($this->mockContainer->reveal());
    $form_state = new FormState();
    $form_state->setValues(['smtp_test_address' => 'test@example.com']);

    $form_array = [];
    $form->submitForm($form_array, $form_state);
  }

}
