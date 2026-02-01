<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to list and manage event registrations.
 */
class RegistrationListForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new RegistrationListForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(Connection $database, MessengerInterface $messenger) {
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Event Registrations');

    // Add CSV export button
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => -1,
    ];

    $form['actions']['export_csv'] = [
      '#type' => 'link',
      '#title' => $this->t('Export to CSV'),
      '#url' => Url::fromRoute('event_registration.csv_export'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    // Get unique event dates for the dropdown
    $event_dates = $this->database->select('event_registration_event', 'ere')
      ->distinct()
      ->fields('ere', ['event_date'])
      ->orderBy('ere.event_date', 'ASC')
      ->execute()
      ->fetchCol();

    $date_options = [];
    foreach ($event_dates as $timestamp) {
      $date_options[$timestamp] = date('Y-m-d', $timestamp);
    }

    $form['event_date_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options,
      '#empty_option' => $this->t('- Select Event Date -'),
      '#ajax' => [
        'callback' => '::loadEventNamesByDate',
        'wrapper' => 'event-names-filter-wrapper',
      ],
    ];

    $form['event_names_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-names-filter-wrapper'],
    ];

    if (!$form_state->getValue('event_date_filter')) {
      $form['event_names_wrapper']['event_name_filter'] = [
        '#type' => 'select',
        '#title' => $this->t('Event Name'),
        '#options' => [],
        '#empty_option' => $this->t('- Select Date First -'),
        '#disabled' => TRUE,
        '#ajax' => [
          'callback' => '::loadRegistrationsTable',
          'wrapper' => 'registrations-table-wrapper',
        ],
      ];
    } else {
      $selected_date = $form_state->getValue('event_date_filter');
      $event_names = $this->getEventNamesByDate($selected_date);

      $name_options = [];
      foreach ($event_names as $id => $name) {
        $name_options[$id] = $name;
      }

      $form['event_names_wrapper']['event_name_filter'] = [
        '#type' => 'select',
        '#title' => $this->t('Event Name'),
        '#options' => $name_options,
        '#empty_option' => $this->t('- Select Event -'),
        '#ajax' => [
          'callback' => '::loadRegistrationsTable',
          'wrapper' => 'registrations-table-wrapper',
        ],
      ];
    }

    $form['filters_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'registrations-table-wrapper'],
    ];

    // Load registrations based on filters
    $event_date_filter = $form_state->getValue('event_date_filter');
    $event_name_filter = $form_state->getValue('event_name_filter');

    $registrations = $this->getFilteredRegistrations($event_date_filter, $event_name_filter);
    $participant_count = count($registrations);

    // Display participant count
    $form['filters_container']['participant_count'] = [
      '#markup' => '<div class="participant-count"><p><strong>' . $this->formatPlural($participant_count, '1 participant', '@count participants') . '</strong></p></div>',
      '#weight' => 5,
    ];

    if (empty($registrations)) {
      $form['filters_container']['message'] = [
        '#markup' => '<p>' . $this->t('No registrations found for the selected criteria.') . '</p>',
        '#weight' => 10,
      ];
    } else {
      // Create a table to display registrations
      $header = [
        $this->t('Name'),
        $this->t('Email'),
        $this->t('Event Date'),
        $this->t('College Name'),
        $this->t('Department'),
        $this->t('Submission Date'),
      ];

      $rows = [];
      foreach ($registrations as $registration) {
        // Get event details
        $event = $this->database->select('event_registration_event', 'ere')
          ->fields('ere', ['event_date'])
          ->condition('ere.id', $registration->event_id)
          ->execute()
          ->fetchObject();

        $rows[] = [
          $registration->full_name,
          $registration->email,
          $event ? date('Y-m-d', $event->event_date) : 'N/A',
          $registration->college,
          $registration->department,
          date('Y-m-d H:i:s', $registration->created),
        ];
      }

      $form['filters_container']['registrations_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No registrations available.'),
        '#weight' => 15,
      ];
    }

    return $form;
  }

  /**
   * AJAX callback to load event names based on selected date.
   */
  public function loadEventNamesByDate(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $selected_date = $form_state->getValue('event_date_filter');
    $event_names = $this->getEventNamesByDate($selected_date);

    $name_options = ['' => $this->t('- Select Event -')];
    foreach ($event_names as $id => $name) {
      $name_options[$id] = $name;
    }

    $form['event_names_wrapper']['event_name_filter']['#options'] = $name_options;
    $form['event_names_wrapper']['event_name_filter']['#value'] = '';
    $form['event_names_wrapper']['event_name_filter']['#disabled'] = empty($name_options);

    $response->addCommand(new HtmlCommand('#event-names-filter-wrapper', $form['event_names_wrapper']));
    $response->addCommand(new InvokeCommand('#registrations-table-wrapper', 'html', ['<div class="messages messages--info">' . $this->t('Please select an event to view registrations.') . '</div>']));

    return $response;
  }

  /**
   * AJAX callback to load registrations table based on filters.
   */
  public function loadRegistrationsTable(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $event_date_filter = $form_state->getValue('event_date_filter');
    $event_name_filter = $form_state->getValue('event_name_filter');

    $registrations = $this->getFilteredRegistrations($event_date_filter, $event_name_filter);
    $participant_count = count($registrations);

    // Generate the participant count markup
    $count_markup = '<div class="participant-count"><p><strong>' . $this->formatPlural($participant_count, '1 participant', '@count participants') . '</strong></p></div>';

    if (empty($registrations)) {
      $table_html = '<p>' . $this->t('No registrations found for the selected criteria.') . '</p>';
    } else {
      // Create table header
      $header = [
        $this->t('Name'),
        $this->t('Email'),
        $this->t('Event Date'),
        $this->t('College Name'),
        $this->t('Department'),
        $this->t('Submission Date'),
      ];

      // Create table rows
      $rows = [];
      foreach ($registrations as $registration) {
        // Get event details
        $event = $this->database->select('event_registration_event', 'ere')
          ->fields('ere', ['event_date'])
          ->condition('ere.id', $registration->event_id)
          ->execute()
          ->fetchObject();

        $rows[] = [
          $registration->full_name,
          $registration->email,
          $event ? date('Y-m-d', $event->event_date) : 'N/A',
          $registration->college,
          $registration->department,
          date('Y-m-d H:i:s', $registration->created),
        ];
      }

      // Build the table HTML
      $table_header = '<thead><tr>';
      foreach ($header as $h) {
        $table_header .= '<th>' . $h . '</th>';
      }
      $table_header .= '</tr></thead>';

      $table_body = '<tbody>';
      foreach ($rows as $row) {
        $table_body .= '<tr>';
        foreach ($row as $cell) {
          $table_body .= '<td>' . $cell . '</td>';
        }
        $table_body .= '</tr>';
      }
      $table_body .= '</tbody>';

      $table_html = '<table class="responsive-enabled">' . $table_header . $table_body . '</table>';
    }

    $full_content = $count_markup . $table_html;
    $response->addCommand(new HtmlCommand('#registrations-table-wrapper', $full_content));

    return $response;
  }

  /**
   * Helper function to get event names by date.
   */
  private function getEventNamesByDate($date) {
    $events = $this->database->select('event_registration_event', 'ere')
      ->fields('ere', ['id', 'event_name'])
      ->condition('ere.event_date', $date)
      ->execute()
      ->fetchAll();

    $result = [];
    foreach ($events as $event) {
      $result[$event->id] = $event->event_name;
    }

    return $result;
  }

  /**
   * Helper function to get filtered registrations.
   */
  private function getFilteredRegistrations($event_date_filter, $event_name_filter) {
    $query = $this->database->select('event_registration_entry', 'ere');
    $query->fields('ere');

    if ($event_date_filter) {
      $query->join('event_registration_event', 'erve', 'ere.event_id = erve.id');
      $query->condition('erve.event_date', $event_date_filter);

      if ($event_name_filter) {
        $query->condition('ere.event_id', $event_name_filter);
      }
    }

    $query->orderBy('ere.created', 'DESC');
    return $query->execute()->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form doesn't have a traditional submit button
    // The export is handled via the link to the CSV controller
  }

}