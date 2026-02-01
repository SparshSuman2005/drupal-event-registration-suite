# Event Registration Module for Drupal 10

A custom Drupal 10 module that allows users to register for events via a custom form, stores registrations, and sends email notifications.

## Installation Steps

1. Place the `event_registration` folder in your Drupal installation's `modules/custom` directory
2. Enable the module through the Drupal admin interface: `admin/modules`
3. Alternatively, use Drush: `drush en event_registration`

## URLs

### Forms
- **Event Registration Form**: `/event-registration`
- **Event Configuration Form**: `/admin/config/event-registration/add-event`

### Admin Pages
- **Event Registration Settings**: `/admin/config/event-registration/settings`
- **Event Registrations List**: `/admin/event-registration/registrations`
- **CSV Export**: `/admin/event-registration/export/csv`

## Database Tables

### `event_registration_event`
Stores event information with the following fields:
- `id`: Primary Key - Unique event ID
- `event_name`: Name of the event
- `category`: Category of the event (Online Workshop, Hackathon, Conference, One-day Workshop)
- `registration_start_date`: Unix timestamp when registration starts
- `registration_end_date`: Unix timestamp when registration ends
- `event_date`: Unix timestamp of the event date
- `status`: Status of the event (1 for active, 0 for inactive)
- `created`: Unix timestamp when the event was created
- `changed`: Unix timestamp when the event was last updated

### `event_registration_entry`
Stores event registration entries with the following fields:
- `id`: Primary Key - Unique registration ID
- `event_id`: Foreign Key - Reference to event_registration_event.id
- `full_name`: Full name of the registrant
- `email`: Email address of the registrant
- `college`: College name of the registrant
- `department`: Department of the registrant
- `created`: Unix timestamp when the registration was created

## Validation and Email Logic

### Validation
- **Duplicate Prevention**: Checks for duplicate registrations using email + event ID combination
- **Special Character Validation**: Prevents special characters in text fields (Full Name, College Name, Department)
- **Date Range Validation**: Ensures registration is only allowed during the event's registration window
- **Required Fields**: All required fields are validated

### Email Notifications
- **User Confirmation**: Sends a confirmation email to the registrant with event details
- **Admin Notification**: Sends an email to the admin (if enabled) with registration details
- **Email Content**: Includes Name, Event Date, Event Name, and Category

## Features

- Dynamic event selection with AJAX-powered cascading dropdowns
- Event scheduling with registration start/end dates
- Administrative configuration for email notifications
- CSV export functionality for all registrations
- Real-time filtering of registrations by event date and name
- Participant count display

## Technical Implementation

- Follows PSR-4 autoloading standards
- Uses Dependency Injection throughout
- Implements Drupal coding standards
- Uses Drupal Form API and AJAX API
- Implements proper database schema with foreign key relationships