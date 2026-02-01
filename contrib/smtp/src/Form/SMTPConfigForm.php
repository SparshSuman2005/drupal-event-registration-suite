<?php

namespace Drupal\smtp\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the SMTP admin settings form.
 */
class SMTPConfigForm extends ConfigFormBase {

  /**
   * Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Email Validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The read only settings service.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $readOnlySettings;

  /**
   * The settings service.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * Constructs a SMTPConfigForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface|null $typed_config
   *   The typed config manager.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   The messenger service.
   * @param \Drupal\Component\Utility\EmailValidatorInterface|null $email_validator
   *   The email validator service.
   * @param \Drupal\Core\Session\AccountProxyInterface|null $current_user
   *   The current user service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ?TypedConfigManagerInterface $typed_config = NULL,
    ?MessengerInterface $messenger = NULL,
    ?EmailValidatorInterface $email_validator = NULL,
    ?AccountProxyInterface $current_user = NULL,
    ?ModuleHandlerInterface $module_handler = NULL,
  ) {

    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      /* @phpstan-ignore-next-line */
      parent::__construct($config_factory);
    }
    else {
      parent::__construct($config_factory, $typed_config);
    }

    if ($messenger == NULL) {
      /* @phpstan-ignore-next-line */
      $messenger = \Drupal::service('messenger');
    }
    $this->messenger = $messenger;

    if ($email_validator == NULL) {
      /* @phpstan-ignore-next-line */
      $email_validator = \Drupal::service('email.validator');
    }
    $this->emailValidator = $email_validator;

    if ($current_user == NULL) {
      /* @phpstan-ignore-next-line */
      $current_user = \Drupal::currentUser();
    }
    $this->currentUser = $current_user;

    if ($module_handler == NULL) {
      /* @phpstan-ignore-next-line */
      $module_handler = \Drupal::moduleHandler();
    }
    $this->moduleHandler = $module_handler;
    $this->readOnlySettings = $config_factory->get('smtp.settings');
    $this->settings = $config_factory->getEditable('smtp.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('messenger'),
      $container->get('email.validator'),
      $container->get('current_user'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smtp_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Don't overwrite the default if MailSystem module is enabled.
    $mailsystem_enabled = $this->moduleHandler->moduleExists('mailsystem');
    $settings = $this->configFactory->get('smtp.settings');
    $smtp_on_value = $settings->get('smtp_on');

    if ($smtp_on_value) {
      $this->messenger->addMessage($this->t('SMTP module is active.'));
      if ($mailsystem_enabled) {
        $this->messenger->addWarning($this->t('SMTP module will use the mailsystem module upon config save.'));
      }
    }
    elseif ($mailsystem_enabled) {
      $this->messenger->addMessage($this->t('SMTP module is managed by <a href=":mailsystem">the mail system module</a>', [':mailsystem' => Url::fromRoute('mailsystem.settings')->toString()]));
    }
    else {
      $this->messenger->addMessage($this->t('SMTP module is INACTIVE.'));
    }
    // Add Debugging warning.
    $log_level = $settings->get('log_level') ?? RfcLogLevel::ERROR;
    if ($log_level === RfcLogLevel::DEBUG) {
      $this->messenger->addWarning($this->t('SMTP debugging is on, ensure <a href="#edit-log-level">log level</a> is changed from Debug before using in production.'));
    }

    if ($mailsystem_enabled) {
      $form['smtp_on']['#type'] = 'value';
      $form['smtp_on']['#value'] = 'mailsystem';
    }
    else {
      $form = [
        '#type'  => 'details',
        '#title' => $this->t('Install options'),
        '#open' => TRUE,
      ];
      $smtp_on_overridden = $settings->hasOverrides('smtp_on');
      $form['smtp_on'] = [
        '#type' => 'radios',
        '#title' => $this->t('Set SMTP as the default mailsystem'),
        '#default_value' => $smtp_on_value ? 'on' : 'off',
        '#options' => [
          'on' => $this->t('On'),
          'off' => $this->t('Off'),
        ],
        '#description' => $smtp_on_overridden ? $this->t('(Overridden) When on, all mail is passed through the SMTP module.') : $this->t('When on, all mail is passed through the SMTP module.'),
        '#disabled' => $smtp_on_overridden,
      ];

      // Force Disabling if PHPmailer doesn't exist.
      if (!class_exists(PHPMailer::class)) {
        $form['smtp_on']['#disabled'] = TRUE;
        $form['smtp_on']['#default_value'] = 'off';
        $form['smtp_on']['#description'] = $this->t('<strong>SMTP cannot be enabled because the PHPMailer library is missing.</strong>');
      }
    }

    $form['log_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Log level'),
      '#options' => [
        RfcLogLevel::EMERGENCY => $this->t('Emergency'),
        RfcLogLevel::ALERT => $this->t('Alert'),
        RfcLogLevel::CRITICAL => $this->t('Critical'),
        RfcLogLevel::ERROR => $this->t('Error'),
        RfcLogLevel::WARNING => $this->t('Warning'),
        RfcLogLevel::NOTICE => $this->t('Notice'),
        RfcLogLevel::INFO => $this->t('Info'),
        RfcLogLevel::DEBUG => $this->t('Debug'),
      ],
      '#default_value' => $settings->get('log_level') ?? RfcLogLevel::ERROR,
      '#description' => $this->t('Choose the appropriate log level. When set to Debug, SMTP debugging will be enabled.'),
    ];
    $smtp_debug_level_overridden = $settings->hasOverrides('smtp_debug_level');
    $form['smtp_debug_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Debug level'),
      '#options' => [
        1 => $this->t('Client (1) - Output messages sent by the client'),
        2 => $this->t('Server (2) - Client messages plus server responses (most useful)'),
        3 => $this->t('Connection (3) - Server level plus connection details (helps diagnose STARTTLS failures)'),
        4 => $this->t('Low level (4) - Connection level plus very verbose low-level information (only for low-level problems)'),
      ],
      '#default_value' => $settings->get('smtp_debug_level'),
      '#description' => $smtp_debug_level_overridden ? $this->t('(Overridden) Choose the appropriate debug verbosity level. Higher numbers are more verbose. Level 2 is recommended for most debugging. This only applies when log level is set to Debug.') : $this->t('Choose the appropriate debug verbosity level. Higher numbers are more verbose. Level 2 is recommended for most debugging. This only applies when log level is set to Debug.'),
      '#disabled' => $smtp_debug_level_overridden,
      '#states' => [
        'visible' => [
          ':input[name="log_level"]' => ['value' => (string) RfcLogLevel::DEBUG],
        ],
      ],
    ];

    $form['server'] = [
      '#type'  => 'details',
      '#title' => $this->t('SMTP server settings'),
      '#open' => TRUE,
    ];
    $smtp_host_overridden = $settings->hasOverrides('smtp_host');
    $form['server']['smtp_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMTP server'),
      '#default_value' => $settings->get('smtp_host'),
      '#description' => $smtp_host_overridden ? $this->t('(Overridden) The address of your outgoing SMTP server.') : $this->t('The address of your outgoing SMTP server.'),
      '#disabled' => $smtp_host_overridden,
    ];
    $smtp_hostbackup_overridden = $settings->hasOverrides('smtp_hostbackup');
    $form['server']['smtp_hostbackup'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMTP backup server'),
      '#default_value' => $settings->get('smtp_hostbackup'),
      '#description' => $smtp_hostbackup_overridden ? $this->t('(Overridden) The address of your outgoing SMTP backup server. If the primary server cannot be found this one will be tried. This is optional.') : $this->t('The address of your outgoing SMTP backup server. If the primary server cannot be found this one will be tried. This is optional.'),
      '#disabled' => $smtp_hostbackup_overridden,
    ];
    $smtp_port_overridden = $settings->hasOverrides('smtp_port');
    $form['server']['smtp_port'] = [
      '#type' => 'number',
      '#title' => $this->t('SMTP port'),
      '#size' => 6,
      '#maxlength' => 6,
      '#default_value' => $settings->get('smtp_port'),
      '#description' => $smtp_port_overridden ? $this->t('(Overridden) The default SMTP port is 25, if that is being blocked try 80. Gmail uses 465. See :url for more information on configuring for use with Gmail.',
      [':url' => 'http://gmail.google.com/support/bin/answer.py?answer=13287']) : $this->t('The default SMTP port is 25, if that is being blocked try 80. Gmail uses 465. See :url for more information on configuring for use with Gmail.',
      [':url' => 'http://gmail.google.com/support/bin/answer.py?answer=13287']),
      '#disabled' => $smtp_port_overridden,
    ];

    $smtp_protocol_overridden = $settings->hasOverrides('smtp_protocol');
    // Only display the option if openssl is installed.
    if (function_exists('openssl_open')) {
      $encryption_options = [
        'standard' => $this->t('No'),
        'ssl' => $this->t('Use SSL'),
        'tls' => $this->t('Use TLS'),
      ];
      $smtp_protocol_value = $settings->get('smtp_protocol');
      $encryption_description = $smtp_protocol_overridden ? $this->t('(Overridden) This allows connection to an SMTP server that requires SSL encryption such as Gmail.') : $this->t('This allows connection to an SMTP server that requires SSL encryption such as Gmail.');
    }
    // If openssl is not installed, use normal protocol.
    else {
      $smtp_protocol_value = 'standard';
      $encryption_options = ['standard' => $this->t('No')];
      $encryption_description = $this->t('Your PHP installation does not have SSL enabled. See the :url page on php.net for more information. Gmail requires SSL.',
        [':url' => 'http://php.net/openssl']);
    }

    $form['server']['smtp_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Use encrypted protocol'),
      '#default_value' => $smtp_protocol_value,
      '#options' => $encryption_options,
      '#description' => $encryption_description,
      '#disabled' => $smtp_protocol_overridden,
    ];

    $smtp_autotls_overridden = $settings->hasOverrides('smtp_autotls');
    $form['server']['smtp_autotls'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable TLS encryption automatically'),
      '#default_value' => $settings->get('smtp_autotls') ? 'on' : 'off',
      '#options' => [
        'on' => $this->t('On'),
        'off' => $this->t('Off'),
      ],
      '#disabled' => $smtp_autotls_overridden,
      '#description' => $smtp_autotls_overridden
        ? $this->t('(Overridden) Automatically enables TLS encryption if the server supports it, even when protocol is set to "standard". Enable this for modern email providers (Gmail, Office365). Disable this for basic mail relays on port 25 without encryption or authentication.')
        : $this->t('Automatically enables TLS encryption if the server supports it, even when protocol is set to "standard". Enable this for modern email providers (Gmail, Office365). Disable this for basic mail relays on port 25 without encryption or authentication.'),
    ];

    $smtp_timeout_overridden = $settings->hasOverrides('smtp_timeout');
    $form['server']['smtp_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#size' => 6,
      '#maxlength' => 6,
      '#default_value' => $settings->get('smtp_timeout'),
      '#description' => $smtp_timeout_overridden ? $this->t('(Overridden) Amount of seconds for the SMTP commands to timeout.') : $this->t('Amount of seconds for the SMTP commands to timeout.'),
      '#disabled' => $smtp_timeout_overridden,
    ];

    $form['auth'] = [
      '#type' => 'details',
      '#title' => $this->t('SMTP Authentication'),
      '#description' => $this->t('Leave blank if your SMTP server does not require authentication.'),
      '#open' => TRUE,
    ];
    $smtp_username_overridden = $settings->hasOverrides('smtp_username');
    $form['auth']['smtp_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $settings->get('smtp_username'),
      '#description' => $smtp_username_overridden ? $this->t('(Overridden) SMTP Username.') : $this->t('SMTP Username.'),
      '#disabled' => $smtp_username_overridden,
    ];
    $smtp_password_overridden = $settings->hasOverrides('smtp_password');
    $form['auth']['smtp_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $settings->get('smtp_password'),
      '#description' => $smtp_password_overridden ? $this->t("(Overridden) SMTP password. If you have already entered your password before, you should leave this field blank, unless you want to change the stored password. Please note that this password will be stored as plain-text inside Drupal's core configuration variables.") : $this->t("SMTP password. If you have already entered your password before, you should leave this field blank, unless you want to change the stored password. Please note that this password will be stored as plain-text inside Drupal's core configuration variables."),
      '#disabled' => $smtp_password_overridden,
    ];

    $form['email_options'] = [
      '#type'  => 'details',
      '#title' => $this->t('E-mail options'),
      '#open' => TRUE,
    ];
    $smtp_from_overridden = $settings->hasOverrides('smtp_from');
    $form['email_options']['smtp_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail from address'),
      '#default_value' => $settings->get('smtp_from'),
      '#description' => $smtp_from_overridden ? $this->t('(Overridden) The e-mail address that all e-mails will be from.') : $this->t('The e-mail address that all e-mails will be from.'),
      '#disabled' => $smtp_from_overridden,
    ];
    $smtp_fromname_overridden = $settings->hasOverrides('smtp_fromname');
    $site_name = $this->configFactory->get('system.site')->get('name');
    $form['email_options']['smtp_fromname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail from name'),
      '#default_value' => $settings->get('smtp_fromname'),
      '#description' => $smtp_fromname_overridden ? $this->t('(Overridden) The name that all e-mails will be from. If left blank will use a default of: @name . Some providers (such as Office365) may ignore this field. For more information, please check SMTP module documentation and your email provider documentation.',
          ['@name' => $site_name]) : $this->t('The name that all e-mails will be from. If left blank will use a default of: @name . Some providers (such as Office365) may ignore this field. For more information, please check SMTP module documentation and your email provider documentation.',
          ['@name' => $site_name]),
      '#disabled' => $smtp_fromname_overridden,
    ];
    $smtp_reroute_address_overridden = $settings->hasOverrides('smtp_reroute_address');
    $form['email_options']['smtp_reroute_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail address to reroute all emails to'),
      '#default_value' => $settings->get('smtp_reroute_address'),
      '#description' => $smtp_reroute_address_overridden ? $this->t('(Overridden) All emails sent by the site will be rerouted to this email address; use with caution.') : $this->t('All emails sent by the site will be rerouted to this email address; use with caution.'),
      '#disabled' => $smtp_reroute_address_overridden,
    ];
    $smtp_allowhtml_overridden = $settings->hasOverrides('smtp_allowhtml');
    $form['email_options']['smtp_allowhtml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow to send e-mails formatted as HTML'),
      '#default_value' => $settings->get('smtp_allowhtml'),
      '#description' => $smtp_allowhtml_overridden ? $this->t('(Overridden) Checking this box will allow HTML formatted e-mails to be sent with the SMTP protocol.') : $this->t('Checking this box will allow HTML formatted e-mails to be sent with the SMTP protocol.'),
      '#disabled' => $smtp_allowhtml_overridden,
    ];
    $form['client'] = [
      '#type'  => 'details',
      '#title' => $this->t('SMTP client settings'),
      '#open' => TRUE,
    ];
    $smtp_client_hostname_overridden = $settings->hasOverrides('smtp_client_hostname');
    $form['client']['smtp_client_hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#default_value' => $settings->get('smtp_client_hostname'),
      '#description' => $smtp_client_hostname_overridden ? $this->t('(Overridden) The hostname to use in the Message-Id and Received headers, and as the default HELO string. Leave blank for using %server_name.',
        ['%server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost.localdomain']) : $this->t('The hostname to use in the Message-Id and Received headers, and as the default HELO string. Leave blank for using %server_name.',
        ['%server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost.localdomain']),
      '#disabled' => $smtp_client_hostname_overridden,
    ];
    $smtp_client_helo_overridden = $settings->hasOverrides('smtp_client_helo');
    $form['client']['smtp_client_helo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HELO'),
      '#default_value' => $settings->get('smtp_client_helo'),
      '#description' => $smtp_client_helo_overridden ? $this->t('(Overridden) The SMTP HELO/EHLO of the message. Defaults to hostname (see above).') : $this->t('The SMTP HELO/EHLO of the message. Defaults to hostname (see above).'),
      '#disabled' => $smtp_client_helo_overridden,
    ];

    $smtp_keepalive_overridden = $settings->hasOverrides('smtp_keepalive');
    $form['server']['smtp_keepalive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Turn on the SMTP keep alive feature'),
      '#default_value' => $settings->get('smtp_keepalive'),
      '#description' => $smtp_keepalive_overridden ? $this->t('(Overridden) Enabling this option will keep the SMTP connection open instead of it being openned and then closed for each mail') : $this->t('Enabling this option will keep the SMTP connection open instead of it being openned and then closed for each mail'),
      '#disabled' => $smtp_keepalive_overridden,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if ($values['smtp_on'] !== 'off' && $values['smtp_host'] == '') {
      $form_state->setErrorByName('smtp_host', $this->t('You must enter an SMTP server address.'));
    }

    if ($values['smtp_on'] !== 'off' && $values['smtp_port'] == '') {
      $form_state->setErrorByName('smtp_port', $this->t('You must enter an SMTP port number.'));
    }

    if ($values['smtp_timeout'] == '' || $values['smtp_timeout'] < 1) {
      $form_state->setErrorByName('smtp_timeout', $this->t('You must enter a Timeout value greater than 0.'));
    }

    if ($values['smtp_from'] && !$this->emailValidator->isValid($values['smtp_from'])) {
      $form_state->setErrorByName('smtp_from', $this->t('The provided from e-mail address is not valid.'));
    }

    if ($values['smtp_reroute_address'] && !$this->emailValidator->isValid($values['smtp_reroute_address'])) {
      $form_state->setErrorByName('smtp_reroute_address', $this->t('The provided reroute e-mail address is not valid.'));
    }

    // If username is set empty, we must set both
    // username/password empty as well.
    if (empty($values['smtp_username'])) {
      $values['smtp_password'] = '';
    }

    // A little hack. When form is presented,
    // the password is not shown (Drupal way of doing).
    // So, if user submits the form without changing the password,
    // we must prevent it from being reset.
    elseif (empty($values['smtp_password'])) {
      $form_state->unsetValue('smtp_password');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('smtp.settings');
    $settings = $this->configFactory->get('smtp.settings');
    $mail_config = $this->configFactory->getEditable('system.mail');
    $mail_system = $mail_config->get('interface.default');

    // Updating config vars.
    if (isset($values['smtp_password']) && !$settings->hasOverrides('smtp_password')) {
      $config->set('smtp_password', $values['smtp_password']);
    }
    if (!$settings->hasOverrides('smtp_on')) {
      $config->set('smtp_on', $values['smtp_on'] == 'on')->save();
    }
    if (!$settings->hasOverrides('smtp_autotls')) {
      $config->set('smtp_autotls', $values['smtp_autotls'] == 'on')->save();
    }
    $config_keys = [
      'smtp_host',
      'smtp_hostbackup',
      'smtp_port',
      'smtp_protocol',
      'smtp_timeout',
      'smtp_username',
      'smtp_from',
      'smtp_fromname',
      'smtp_client_hostname',
      'smtp_client_helo',
      'smtp_allowhtml',
      'smtp_reroute_address',
      'smtp_debug_level',
      'smtp_keepalive',
      'log_level',
    ];
    foreach ($config_keys as $name) {
      if (!$settings->hasOverrides($name)) {
        $config->set($name, $values[$name])->save();
      }
    }

    // Set as default mail system if module is enabled.
    if ($config->get('smtp_on') ||
        ($settings->hasOverrides('smtp_on') && $values['smtp_on'] == 'on')) {
      if ($mail_system != 'SMTPMailSystem') {
        $config->set('prev_mail_system', $mail_system)->save();
        $mail_system = 'SMTPMailSystem';
        $mail_config->set('interface.default', $mail_system)->save();
      }

    }
    else {
      $default_system_mail = 'php_mail';
      $default_interface = $config->get('prev_mail_system') ?? $default_system_mail;
      $mail_config->set('interface.default', $default_interface)->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'smtp.settings',
    ];
  }

}
