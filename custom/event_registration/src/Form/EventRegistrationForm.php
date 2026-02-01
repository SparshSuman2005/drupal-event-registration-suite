<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\event_registration\Service\MailService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventRegistrationForm extends FormBase {

  protected Connection $database;
  protected MailService $mailService;

  public function __construct(
    Connection $database,
    MailService $mail_service
  ) {
    $this->database = $database;
    $this->mailService = $mail_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('event_registration.mail_service')
    );
  }

  public function getFormId() {
    return 'event_registration_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_time = time();

    /* ================= CATEGORY ================= */

    $categories = $this->database->select('event_registration_event', 'e')
      ->distinct()
      ->fields('e', ['category'])
      ->condition('status', 1)
      ->condition('registration_start_date', $current_time, '<=')
      ->condition('registration_end_date', $current_time, '>=')
      ->execute()
      ->fetchCol();

    $category_options = ['' => $this->t('- Select Category -')];
    foreach ($categories as $category) {
      $category_options[$category] = $category;
    }

    $selected_category = $form_state->getValue('category');
    // Use containers with proper structure for AJAX
    // Get all possible dates and events for #states API
    $all_dates_by_category = [];
    $all_events_by_category_and_date = [];

    foreach (array_keys($category_options) as $cat) {
      if (!empty($cat)) { // Skip the empty option
        $dates = $this->getEventDatesByCategory($cat);
        $all_dates_by_category[$cat] = [];

        foreach ($dates as $timestamp) {
          $all_dates_by_category[$cat][$timestamp] = date('Y-m-d', $timestamp);

          // Get events for this category and date
          $events = $this->getEventNamesByCategoryAndDate($cat, $timestamp);
          $all_events_by_category_and_date[$cat][$timestamp] = $events;
        }
      }
    }

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#required' => TRUE,
      '#options' => $category_options,
      '#default_value' => $selected_category ?: '',
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    ];

    /* ================= EVENT DATE ================= */

    // Prepare options for all categories
    $date_options_all = ['' => $this->t('- Select Category First -')];
    foreach ($all_dates_by_category as $cat => $dates) {
      foreach ($dates as $timestamp => $date_str) {
        $date_options_all[$timestamp] = $date_str;
      }
    }

    $form['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options_all,
      '#default_value' => $selected_category ? ($form_state->getValue('event_date') ?: '') : '',
      '#states' => [
        'visible' => [
          ':input[name="category"]' => ['!value' => ''],
        ],
        'required' => [
          ':input[name="category"]' => ['!value' => ''],
        ],
        'enabled' => [
          ':input[name="category"]' => ['!value' => ''],
        ],
      ],
    ];

    // Set conditional options based on selected category
    if ($selected_category && isset($all_dates_by_category[$selected_category])) {
      $form['event_date']['#options'] = ['' => $this->t('- Select Date -')] + $all_dates_by_category[$selected_category];
    }

    /* ================= EVENT NAME ================= */

    // Prepare options for all category-date combinations
    $name_options_all = ['' => $this->t('- Select Date First -')];
    foreach ($all_events_by_category_and_date as $cat => $dates) {
      foreach ($dates as $date => $events) {
        foreach ($events as $id => $name) {
          $name_options_all[$id] = $name;
        }
      }
    }

    $form['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $name_options_all,
      '#default_value' => ($selected_category && $form_state->getValue('event_date')) ? ($form_state->getValue('event_name') ?: '') : '',
      '#states' => [
        'visible' => [
          ':input[name="event_date"]' => ['!value' => ''],
        ],
        'required' => [
          ':input[name="event_date"]' => ['!value' => ''],
        ],
        'enabled' => [
          ':input[name="event_date"]' => ['!value' => ''],
        ],
      ],
    ];

    // Set conditional options based on selected category and date
    $selected_date = $form_state->getValue('event_date');
    if ($selected_category && $selected_date &&
        isset($all_events_by_category_and_date[$selected_category][$selected_date])) {
      $form['event_name']['#options'] = ['' => $this->t('- Select Event -')] +
                                        $all_events_by_category_and_date[$selected_category][$selected_date];
    }

    /* ================= USER FIELDS ================= */

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['college'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }


  /* ================= HELPERS ================= */

  private function getEventDatesByCategory($category) {
    try {
      return $this->database->select('event_registration_event', 'e')
        ->distinct()
        ->fields('e', ['event_date'])
        ->condition('category', $category)
        ->condition('status', 1)
        ->execute()
        ->fetchCol();
    } catch (\Exception $e) {
      \Drupal::logger('event_registration')->error('Error getting event dates by category: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  private function getEventNamesByCategoryAndDate($category, $date) {
    try {
      $events = $this->database->select('event_registration_event', 'e')
        ->fields('e', ['id', 'event_name'])
        ->condition('category', $category)
        ->condition('event_date', $date)
        ->condition('status', 1)
        ->execute()
        ->fetchAll();

      $result = [];
      foreach ($events as $event) {
        $result[$event->id] = $event->event_name;
      }
      return $result;
    } catch (\Exception $e) {
      \Drupal::logger('event_registration')->error('Error getting event names by category and date: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /* ================= VALIDATION ================= */

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pattern = '/[^a-zA-Z\s]/';

    foreach (['full_name', 'college', 'department'] as $field) {
      $value = $form_state->getValue($field);
      if (!empty($value) && preg_match($pattern, $value)) {
        $form_state->setErrorByName($field, $this->t('Special characters are not allowed.'));
      }
    }

    // Validate dependent fields only during submission
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = !empty($triggering_element) ? $triggering_element['#name'] : NULL;

    // Only validate dependent fields when the form is being submitted
    $is_submit_trigger = !empty($triggering_element) &&
                         (isset($triggering_element['#submit']) ||
                          (isset($triggering_element['#value']) && $triggering_element['#value'] === $this->t('Register')));

    $category = $form_state->getValue('category');
    $event_date = $form_state->getValue('event_date');
    $event_name = $form_state->getValue('event_name');

    if (!empty($category) && empty($event_date)) {
      $form_state->setErrorByName('event_date', $this->t('Please select an event date.'));
    }

    if (!empty($category) && !empty($event_date) && empty($event_name)) {
      $form_state->setErrorByName('event_name', $this->t('Please select an event name.'));
    }

    // Validate that event_name is a valid ID (not empty and numeric)
    if (!empty($event_name) && (!is_numeric($event_name) || $event_name <= 0)) {
      $form_state->setErrorByName('event_name', $this->t('Please select a valid event.'));
    }
  }

  /* ================= SUBMIT ================= */

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event_name_value = $form_state->getValue('event_name');
    $email = $form_state->getValue('email');

    $event = $this->database->select('event_registration_event', 'e')
      ->fields('e', ['event_name', 'event_date', 'category'])
      ->condition('id', $event_name_value)
      ->execute()
      ->fetchAssoc();

    $this->database->insert('event_registration_entry')->fields([
      'event_id' => $event_name_value,
      'full_name' => $form_state->getValue('full_name'),
      'email' => $email,
      'college' => $form_state->getValue('college'),
      'department' => $form_state->getValue('department'),
      'created' => time(),
    ])->execute();

    $registration_data = array_merge($event, [
      'full_name' => $form_state->getValue('full_name'),
      'email' => $email,
      'college' => $form_state->getValue('college'),
      'department' => $form_state->getValue('department'),
    ]);

    $this->mailService->sendConfirmationEmail($email, $registration_data);

    // Send admin notification if enabled
    $config = \Drupal::config('event_registration.settings');
    if ($config->get('admin_notifications_enabled')) {
      $admin_email = $config->get('admin_notification_email');
      // If no specific admin email is set, use the site-wide email
      if (empty($admin_email)) {
        $site_config = \Drupal::config('system.site');
        $admin_email = $site_config->get('mail');
      }

      if (!empty($admin_email)) {
        $this->mailService->sendAdminNotification($admin_email, $registration_data);
      }
    }

    $this->messenger()->addStatus($this->t('Registration successful.'));
  }

}
