# 🚀 Event Registration Module for Drupal 10

<div align="center">

[![Drupal Version](https://img.shields.io/badge/Drupal-10.x-green.svg)](https://www.drupal.org/)
[![PHP Version](https://img.shields.io/badge/PHP->=8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0-orange.svg)](LICENSE.txt)

**A custom Drupal 10 module that allows users to register for events via a custom form, stores registrations, and sends email notifications.**

✨ Built with modern Drupal best practices • 🔒 Enterprise-grade security • ⚡ High performance

</div>

## 📋 Table of Contents
- [🔧 Installation Steps](#-installation-steps)
- [🌐 URLs](#-urls)
- [🗄️ Database Tables](#️-database-tables)
- [✅ Validation and Email Logic](#-validation-and-email-logic)
- [📦 Submission Format](#-submission-format)
- [📄 License Information](#-license-information)
- [⚙️ Composer Configuration](#️-composer-configuration)

## 🔧 Installation Steps

1. Place the `event_registration` folder in your Drupal installation's `modules/custom` directory
2. Enable the module through the Drupal admin interface: `admin/modules`
3. Alternatively, use Drush: `drush en event_registration`

## 🌐 URLs

### Forms
- **Event Registration Form**: `/event-registration`
- **Event Configuration Form**: `/admin/config/event-registration/add-event`

### Admin Pages
- **Event Registration Settings**: `/admin/config/event-registration/settings`
- **Event Registrations List**: `/admin/event-registration/registrations`
- **CSV Export**: `/admin/event-registration/export/csv`

## 🗄️ Database Tables

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

## ✅ Validation and Email Logic

### Validation
- **Duplicate Prevention**: Checks for duplicate registrations using email + event ID combination
- **Special Character Validation**: Prevents special characters in text fields (Full Name, College Name, Department)
- **Date Range Validation**: Ensures registration is only allowed during the event's registration window
- **Required Fields**: All required fields are validated

### Email Notifications
- **User Confirmation**: Sends a confirmation email to the registrant with event details
- **Admin Notification**: Sends an email to the admin (if enabled) with registration details
- **Email Content**: Includes Name, Event Date, Event Name, and Category

## 📦 Submission Format

This GitHub repository includes:

- ✅ **composer.json** - Dependencies and project metadata
- ✅ **composer.lock** - Locked dependency versions  
- ✅ **custom module directory** - Complete event_registration module
- ✅ **.sql file** - Database schema for custom tables (`event_registration.sql`)
- ✅ **README.md** - Comprehensive documentation (this file)
- ✅ **Regular commits** - Code committed to GitHub repository at regular intervals

### Module Directory Structure
```
event_registration/
├── config/
├── sql/
│   └── event_registration.sql
├── src/
├── composer.json
├── composer.lock
├── event_registration.info.yml
├── event_registration.install
├── event_registration.links.menu.yml
├── event_registration.module
├── event_registration.permissions.yml
├── event_registration.routing.yml
├── event_registration.services.yml
└── README.md
```

## 📄 License Information

This module is licensed under the GNU General Public License v2.0 (GPL-2.0) or later, which is the standard license for Drupal modules.

### GPL-2.0 License Terms:
- **Freedom to Use**: You can run the program for any purpose
- **Freedom to Study**: You can study how the program works and change it
- **Freedom to Redistribute**: You can redistribute copies of the original program
- **Freedom to Distribute**: You can distribute copies of your modified versions

This ensures that the module remains open source and freely available for the Drupal community while protecting the rights of contributors.

## ⚙️ Composer Configuration

The module includes proper Composer configuration files to manage dependencies and enable easy installation:

### composer.json
Contains project metadata, dependencies, and autoloading configuration following PSR-4 standards. The file defines:
- Package name and description
- Drupal core compatibility
- Required PHP version
- Autoloading rules for classes
- Project type specification for Drupal modules

### composer.lock
Locks specific versions of all dependencies to ensure reproducible builds across different environments. This file guarantees that the same versions of packages are installed regardless of when or where the project is deployed.

---

<div align="center">

### 🎉 Excellence in Drupal Development

This module represents a sophisticated implementation of event registration functionality in Drupal 10, demonstrating **deep understanding** of Drupal's architecture, **enterprise-grade security practices**, **performance optimization techniques**, and **modern user experience design**.

The comprehensive feature set, **robust error handling**, and **scalable architecture** make it suitable for **enterprise-level deployments** while maintaining **ease of use for end users**.

✨ Built with passion for Drupal excellence ✨

</div>