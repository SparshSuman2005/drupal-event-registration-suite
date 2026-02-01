<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides a CSV export controller for event registrations.
 */
class CsvExportController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new CsvExportController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(Connection $database, FileSystemInterface $file_system, MessengerInterface $messenger) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('messenger')
    );
  }

  /**
   * Exports event registrations as CSV.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   A streamed response with CSV content.
   */
  public function export() {
    $callback = function () {
      $handle = fopen('php://output', 'w+');

      // Add UTF-8 BOM to ensure proper character encoding in Excel
      fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

      // Define the header row with all required fields
      fputcsv($handle, [
        $this->t('Name')->render(),
        $this->t('Email')->render(),
        $this->t('Event Date')->render(),
        $this->t('College Name')->render(),
        $this->t('Department')->render(),
        $this->t('Submission Date')->render(),
      ]);

      // Query the database for all event registrations with event details
      $query = $this->database->select('event_registration_entry', 'ere');
      $query->join('event_registration_event', 'erve', 'ere.event_id = erve.id');
      $query->fields('ere', ['full_name', 'email', 'college', 'department', 'created']);
      $query->fields('erve', ['event_date']);
      $query->orderBy('ere.created', 'DESC');

      $results = $query->execute();

      // Process each registration record
      while ($record = $results->fetchObject()) {
        fputcsv($handle, [
          $record->full_name,
          $record->email,
          date('Y-m-d', $record->event_date),
          $record->college,
          $record->department,
          date('Y-m-d H:i:s', $record->created),
        ]);
      }

      fclose($handle);
    };

    // Set headers for CSV download
    $filename = 'event_registrations_' . date('Y-m-d_H-i-s') . '.csv';

    $response = new StreamedResponse($callback);
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
    $response->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

    return $response;
  }

}
