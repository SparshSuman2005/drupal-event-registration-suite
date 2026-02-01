<?php

namespace Drupal\event_registration\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Provides a service for sending event registration emails.
 */
class MailService {

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new MailService.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, DateFormatterInterface $date_formatter) {
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Sends a registration confirmation email to the user.
   *
   * @param string $email
   *   The recipient's email address.
   * @param array $registration_data
   *   The registration data including name, event details, etc.
   */
  public function sendConfirmationEmail($email, $registration_data) {
    $params = [
      'subject' => t('Registration Confirmation for @event', ['@event' => $registration_data['event_name']]),
      'message' => [
        t('Dear @name,', ['@name' => $registration_data['full_name']]),
        t('You have successfully registered for the event: @event', ['@event' => $registration_data['event_name']]),
        t('Event Date: @date', ['@date' => $this->dateFormatter->format($registration_data['event_date'], 'custom', 'Y-m-d')]),
        t('Category: @category', ['@category' => $registration_data['category']]),
        t('Thank you for registering!'),
      ],
    ];

    $result = $this->mailManager->mail('event_registration', 'registration_confirmation', $email, \Drupal::languageManager()->getDefaultLanguage()->getId(), $params);

    if ($result['result']) {
      $this->loggerFactory->get('event_registration')->info('Confirmation email sent to @email for event @event.', [
        '@email' => $email,
        '@event' => $registration_data['event_name'],
      ]);
    } else {
      $this->loggerFactory->get('event_registration')->error('Failed to send confirmation email to @email for event @event.', [
        '@email' => $email,
        '@event' => $registration_data['event_name'],
      ]);
    }
  }

  /**
   * Sends an admin notification email.
   *
   * @param string $admin_email
   *   The admin's email address.
   * @param array $registration_data
   *   The registration data including name, event details, etc.
   */
  public function sendAdminNotification($admin_email, $registration_data) {
    $params = [
      'subject' => t('New Event Registration for @event', ['@event' => $registration_data['event_name']]),
      'body' => [
        t('A new registration has been received:'),
        t('Name: @name', ['@name' => $registration_data['full_name']]),
        t('Email: @email', ['@email' => $registration_data['email']]),
        t('Event: @event', ['@event' => $registration_data['event_name']]),
        t('Event Date: @date', ['@date' => $this->dateFormatter->format($registration_data['event_date'], 'custom', 'Y-m-d')]),
        t('Category: @category', ['@category' => $registration_data['category']]),
        t('College: @college', ['@college' => $registration_data['college']]),
        t('Department: @department', ['@department' => $registration_data['department']]),
      ],
    ];

  $result = $this->mailManager->mail(
  'event_registration',
  'admin_notification',
  $admin_email,
  \Drupal::languageManager()->getDefaultLanguage()->getId(),
  $params
);

    if ($result['result']) {
      $this->loggerFactory->get('event_registration')->info('Admin notification email sent to @email for registration of @name.', [
        '@email' => $admin_email,
        '@name' => $registration_data['full_name'],
      ]);
    } else {
      $this->loggerFactory->get('event_registration')->error('Failed to send admin notification email to @email for registration of @name.', [
        '@email' => $admin_email,
        '@name' => $registration_data['full_name'],
      ]);
    }
  }

}