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

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#required' => TRUE,
      '#options' => $category_options,
      '#ajax' => [
        'callback' => '::updateEventDates',
        'wrapper' => 'event-date-wrapper',
      ],
    ];

    /* ================= EVENT DATE ================= */

    $form['event_date_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-date-wrapper'],
    ];

    $selected_category = $form_state->getValue('category');
    $date_options = ['' => $this->t('- Select Date -')];

    if ($selected_category) {
      $dates = $this->getEventDatesByCategory($selected_category);
      foreach ($dates as $timestamp) {
        $date_options[$timestamp] = date('Y-m-d', $timestamp);
      }
    }

    $form['event_date_wrapper']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options,
      '#disabled' => empty($selected_category),
      '#required' => !empty($selected_category),
      '#ajax' => [
        'callback' => '::updateEventNames',
        'wrapper' => 'event-name-wrapper',
      ],
    ];

    /* ================= EVENT NAME ================= */

    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    $selected_date = $form_state->getValue('event_date');
    $name_options = ['' => $this->t('- Select Event -')];

    if ($selected_category && $selected_date) {
      $events = $this->getEventNamesByCategoryAndDate($selected_category, $selected_date);
      foreach ($events as $id => $name) {
        $name_options[$id] = $name;
      }
    }

    $form['event_name_wrapper']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $name_options,
      '#disabled' => empty($selected_date),
      '#required' => !empty($selected_date),
    ];

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

  /* ================= AJAX CALLBACKS ================= */

  public function updateEventDates(array &$form, FormStateInterface $form_state) {
    return $form['event_date_wrapper'];
  }

  public function updateEventNames(array &$form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  /* ================= HELPERS ================= */

  private function getEventDatesByCategory($category) {
    return $this->database->select('event_registration_event', 'e')
      ->distinct()
      ->fields('e', ['event_date'])
      ->condition('category', $category)
      ->condition('status', 1)
      ->execute()
      ->fetchCol();
  }

  private function getEventNamesByCategoryAndDate($category, $date) {
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
  }

  /* ================= VALIDATION ================= */

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pattern = '/[^a-zA-Z\s]/';

    foreach (['full_name', 'college', 'department'] as $field) {
      if (preg_match($pattern, $form_state->getValue($field))) {
        $form_state->setErrorByName($field, $this->t('Special characters are not allowed.'));
      }
    }
  }

  /* ================= SUBMIT ================= */

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event_id = $form_state->getValue('event_name');
    $email = $form_state->getValue('email');

    $event = $this->database->select('event_registration_event', 'e')
      ->fields('e', ['event_name', 'event_date', 'category'])
      ->condition('id', $event_id)
      ->execute()
      ->fetchAssoc();

    $this->database->insert('event_registration_entry')->fields([
      'event_id' => $event_id,
      'full_name' => $form_state->getValue('full_name'),
      'email' => $email,
      'college' => $form_state->getValue('college'),
      'department' => $form_state->getValue('department'),
      'created' => time(),
    ])->execute();

    $this->mailService->sendConfirmationEmail($email, array_merge($event, [
      'full_name' => $form_state->getValue('full_name'),
      'email' => $email,
      'college' => $form_state->getValue('college'),
      'department' => $form_state->getValue('department'),
    ]));

    $this->messenger()->addStatus($this->t('Registration successful.'));
  }

}
