<?php

declare(strict_types=1);

namespace Drupal\smtp\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\smtp\Config\SmtpOverride;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the SMTP test form.
 */
final class TestForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The SMTP settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new TestForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    EmailValidatorInterface $email_validator,
    AccountProxyInterface $current_user,
    MailManagerInterface $mail_manager,
  ) {
    $this->configFactory = $config_factory;
    $this->settings = $config_factory->get('smtp.settings');
    $this->emailValidator = $email_validator;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('email.validator'),
      $container->get('current_user'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smtp_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $reroute_address = $this->settings->get('smtp_reroute_address');
    if ($reroute_address) {
      $this->messenger->addWarning($this->t('All emails sent by the site will be rerouted to this email address; use with caution.'));
    }
    $form['smtp_test_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail address to send a test e-mail to'),
      '#default_value' => !empty($reroute_address) ? $reroute_address : $this->currentUser->getEmail(),
      '#disabled' => !empty($reroute_address),
      '#description' => $this->t('Type in an address to have a test e-mail sent there.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test e-mail'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $test_address = $form_state->getValue('smtp_test_address');
    if (!$this->emailValidator->isValid($test_address)) {
      $form_state->setErrorByName('smtp_test_address', $this->t('The provided test e-mail address, @address, is not valid.', [
        '@address' => $test_address,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $test_address = $form_state->getValue('smtp_test_address');

    $params['subject'] = $this->t('Drupal SMTP test e-mail');
    $params['body'] = [$this->t('If you receive this message it means your site is capable of using SMTP to send e-mail.')];

    $original_interface = $this->configFactory->get('system.mail')->get('interface.default');
    if ($original_interface !== 'SMTPMailSystem') {
      $this->configFactory->addOverride(new SmtpOverride());
      $this->messenger->addStatus($this->t('The system.mail was overridden to use SMTPMailSystem for this request only.'));
    }

    $email_langcode = $this->currentUser->getPreferredLangcode();
    if ($this->mailManager->mail('smtp', 'smtp-test', $test_address, $email_langcode, $params)) {
      $this->messenger->addMessage($this->t('A test e-mail has been sent to @email via SMTP. You may want to check the log for any error messages.', ['@email' => $test_address]));
    }
  }

}
