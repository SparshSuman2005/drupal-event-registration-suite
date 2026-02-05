# Event Registration Module

A Drupal 10 module that provides comprehensive event registration functionality with email notifications, admin management, and CSV export capabilities.

## Features

- **Event Creation**: Admins can create events with categories, dates, and registration periods
- **User Registration**: Users can register for events through a dedicated form
- **Email Notifications**: Automatic confirmation emails to registrants and admin notifications
- **Admin Management**: Comprehensive admin interface for managing events and registrations
- **CSV Export**: Export all registrations to CSV format
- **Field Validation**: Input validation for user data
- **Responsive Design**: Works across devices

## File Structure

```
event_registration/
├── config/
├── sql/
├── src/
│   ├── Controller/
│   │   └── CsvExportController.php
│   ├── Form/
│   │   ├── AdminSettingsForm.php
│   │   ├── EventConfigForm.php
│   │   ├── EventRegistrationForm.php
│   │   └── RegistrationListForm.php
│   └── Service/
│       └── MailService.php
├── event_registration.info.yml
├── event_registration.install
├── event_registration.links.menu.yml
├── event_registration.module
├── event_registration.permissions.yml
├── event_registration.routing.yml
└── event_registration.services.yml
```

## Installation

1. Place the `event_registration` folder in your Drupal modules directory (`modules/custom/`)
2. Navigate to `Extend` in your Drupal admin panel
3. Find and enable the "Event Registration" module
4. Configure settings at `/admin/config/event-registration/settings`

## Configuration

After installation, configure the module at `/admin/config/event-registration/settings`:

- Set up admin notification preferences
- Configure email templates
- Manage module settings

## Permissions

The module defines the following permissions:

- **Administer event registration**: Full access to configure settings, manage events, and view all registrations
- **Access event registration**: Allows users to view and register for events
- **Manage own event registrations**: Allows users to edit and cancel their own registrations

## Usage

### Creating Events
1. Go to `/admin/config/event-registration/add-event`
2. Fill in event details (name, category, dates)
3. Save the event

### Registering for Events
1. Users can access the registration form at `/event-registration`
2. Select event category, date, and specific event
3. Fill in personal details
4. Submit the form

### Managing Registrations
1. View all registrations at `/admin/event-registration/registrations`
2. Export data as CSV at `/admin/event-registration/export/csv`

## Dependencies

- Drupal 10.x
- Standard Drupal core modules

## Database Schema

The module creates two tables:
- `event_registration_event`: Stores event information
- `event_registration_entry`: Stores individual registration entries

## Email Templates

The module supports customizable email notifications:
- Registration confirmation emails to users
- Admin notifications for new registrations

## Troubleshooting

If you encounter issues:
1. Check Drupal logs at `/admin/reports/dblog`
2. Ensure all required permissions are set correctly
3. Verify that cron jobs are running properly for scheduled tasks

## License

This project is licensed under the GPL v2.0 License.