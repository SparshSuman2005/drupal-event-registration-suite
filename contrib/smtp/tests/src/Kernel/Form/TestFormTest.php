<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\smtp\Form\TestForm;

/**
 * Kernel tests for TestForm.
 *
 * @group smtp
 */
class TestFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['smtp', 'system', 'user'];

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'smtp']);
    $this->installEntitySchema('user');

    $this->formBuilder = $this->container->get('form_builder');
    $this->configFactory = $this->container->get('config.factory');

    // Set up default SMTP configuration.
    $this->configFactory->getEditable('smtp.settings')
      ->set('smtp_on', FALSE)
      ->set('smtp_host', 'smtp.example.com')
      ->set('smtp_port', '587')
      ->set('smtp_protocol', 'tls')
      ->set('smtp_username', 'user@example.com')
      ->set('smtp_password', 'password')
      ->set('smtp_from', 'from@example.com')
      ->set('smtp_fromname', 'Test Site')
      ->set('smtp_reroute_address', '')
      ->save();
  }

  /**
   * Tests the form ID.
   */
  public function testGetFormId(): void {
    $form = TestForm::create($this->container);
    $this->assertEquals('smtp_test_form', $form->getFormId());
  }

  /**
   * Tests form structure without reroute address.
   */
  public function testBuildFormStructure(): void {
    $form = $this->formBuilder->getForm(TestForm::class);

    $this->assertArrayHasKey('smtp_test_address', $form);
    $this->assertEquals('textfield', $form['smtp_test_address']['#type']);
    $this->assertTrue($form['smtp_test_address']['#required']);
    $this->assertFalse($form['smtp_test_address']['#disabled']);

    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayHasKey('submit', $form['actions']);
    $this->assertEquals('submit', $form['actions']['submit']['#type']);
  }

  /**
   * Tests form structure with reroute address.
   */
  public function testBuildFormWithRerouteAddress(): void {
    // Set reroute address.
    $this->configFactory->getEditable('smtp.settings')
      ->set('smtp_reroute_address', 'reroute@example.com')
      ->save();

    $form = $this->formBuilder->getForm(TestForm::class);

    $this->assertArrayHasKey('smtp_test_address', $form);
    $this->assertEquals('reroute@example.com', $form['smtp_test_address']['#default_value']);
    $this->assertTrue($form['smtp_test_address']['#disabled']);
  }

  /**
   * Tests form validation with valid email.
   */
  public function testValidateFormValidEmail(): void {
    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => 'test@example.com',
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertEmpty($errors, 'Valid email should not cause validation errors.');
  }

  /**
   * Tests form validation with invalid email.
   */
  public function testValidateFormInvalidEmail(): void {
    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => 'invalid-email',
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('smtp_test_address', $errors);
  }

  /**
   * Tests form submission when SMTP is enabled.
   */
  public function testSubmitFormSmtpEnabled(): void {
    // Enable SMTP.
    $this->configFactory->getEditable('smtp.settings')
      ->set('smtp_on', TRUE)
      ->save();

    // Get initial SMTP settings.
    $initial_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();

    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => '',
    ]);

    $form_array = [];
    $form_object->submitForm($form_array, $form_state);

    // Clear the cached config.
    \Drupal::service('config.factory')->clearStaticCache();

    // SMTP settings should not be changed.
    $final_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();
    $this->assertEquals($initial_smtp_settings, $final_smtp_settings, 'SMTP settings should not be changed by form submission.');
  }

  /**
   * Tests form submission when SMTP is disabled.
   */
  public function testSubmitFormSmtpDisabled(): void {
    // Ensure SMTP is disabled.
    $this->configFactory->getEditable('smtp.settings')
      ->set('smtp_on', FALSE)
      ->save();

    // Get initial SMTP settings.
    $initial_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();

    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => '',
    ]);

    $form_array = [];
    $form_object->submitForm($form_array, $form_state);

    // Clear the cached config.
    \Drupal::service('config.factory')->clearStaticCache();

    // SMTP settings should not be changed.
    $final_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();
    $this->assertEquals($initial_smtp_settings, $final_smtp_settings, 'SMTP settings should not be changed by form submission.');
  }

  /**
   * Tests form submission does not modify SMTP settings.
   */
  public function testSubmitFormDoesNotModifySmtpSettings(): void {
    // Get original settings.
    $original_settings = $this->configFactory->get('smtp.settings')->getRawData();

    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => 'test@example.com',
    ]);

    $form_array = [];
    $form_object->submitForm($form_array, $form_state);

    // Clear the cached config.
    \Drupal::service('config.factory')->clearStaticCache();

    // Reload settings and verify they haven't changed.
    $current_settings = $this->configFactory->get('smtp.settings')->getRawData();
    $this->assertEquals($original_settings, $current_settings, 'SMTP settings should not be modified by test form submission.');
  }

  /**
   * Tests form submission with different SMTP states.
   */
  public function testSubmitFormWithDifferentSmtpStates(): void {
    $smtp_states = [TRUE, FALSE];

    foreach ($smtp_states as $smtp_on) {
      // Set SMTP state.
      $this->configFactory->getEditable('smtp.settings')
        ->set('smtp_on', $smtp_on)
        ->save();

      // Get initial SMTP settings.
      $initial_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();

      // Clear the cached config.
      \Drupal::service('config.factory')->clearStaticCache();

      $form_object = TestForm::create($this->container);
      $form_state = (new FormState())->setValues([
        'smtp_test_address' => '',
      ]);

      $form_array = [];
      $form_object->submitForm($form_array, $form_state);

      // Clear the cached config again to get the latest value.
      \Drupal::service('config.factory')->clearStaticCache();

      // Verify SMTP settings are not changed.
      $final_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();
      $smtp_state_label = $smtp_on ? 'enabled' : 'disabled';
      $this->assertEquals($initial_smtp_settings, $final_smtp_settings, "SMTP settings should not be changed when SMTP is {$smtp_state_label}");
    }
  }

  /**
   * Tests form with empty test address does not send email.
   */
  public function testSubmitFormWithEmptyAddress(): void {
    // Get initial SMTP settings.
    $original_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();

    $form_object = TestForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'smtp_test_address' => '',
    ]);

    $form_array = [];
    $form_object->submitForm($form_array, $form_state);

    // Clear the cached config.
    \Drupal::service('config.factory')->clearStaticCache();

    // SMTP settings should not be changed.
    $current_smtp_settings = $this->configFactory->get('smtp.settings')->getRawData();
    $this->assertEquals($original_smtp_settings, $current_smtp_settings, 'SMTP settings should not be changed when no email address is provided.');
  }

  /**
   * Tests form integration with config system.
   */
  public function testConfigIntegration(): void {
    $smtp_config = $this->configFactory->get('smtp.settings');

    // Verify config is loaded.
    $this->assertNotNull($smtp_config);
    $this->assertEquals('smtp.example.com', $smtp_config->get('smtp_host'));
    $this->assertEquals('587', $smtp_config->get('smtp_port'));

    // Verify form can access config.
    $form = TestForm::create($this->container);
    $this->assertInstanceOf(TestForm::class, $form);
  }

}
