# Upgrade Guide

This document provides instructions for upgrading the Event Registration Module for Drupal 10.

## Upgrading from Previous Versions

### Before Upgrading

1. **Backup Your Site**: Always create a complete backup of your site files and database before upgrading.

2. **Check Compatibility**: Ensure your Drupal core version is compatible with the new module version.

3. **Review Release Notes**: Check the CHANGELOG.md file for any breaking changes or deprecations.

4. **Test in Staging**: Perform the upgrade on a staging site first before upgrading production.

### Standard Upgrade Process

1. **Download the New Version**: Obtain the latest version of the module from the official source.

2. **Disable the Module**:
   ```bash
   drush pm-uninstall event_registration
   ```
   Or via the Drupal admin interface at `admin/modules/uninstall`.

3. **Replace Module Files**: Replace the old module files with the new version.

4. **Clear Cache**: Clear Drupal's cache:
   ```bash
   drush cr
   ```

5. **Run Database Updates**: Execute database updates:
   ```bash
   drush updb
   ```
   Or visit `admin/modules/update` in the admin interface.

6. **Re-enable the Module**:
   ```bash
   drush en event_registration
   ```

7. **Verify Functionality**: Test all module features to ensure they work correctly.

### Version-Specific Upgrade Instructions

#### Upgrading to 1.0.0

- Initial release - no previous versions to upgrade from
- Simply install the module following standard Drupal module installation procedures

### Post-Upgrade Tasks

1. **Check Configuration**: Verify that all module settings are preserved after the upgrade.

2. **Test Forms**: Ensure event registration forms and administrative forms work correctly.

3. **Verify Email Notifications**: Test that email notifications are still functioning.

4. **Review Permissions**: Confirm that user permissions are correctly applied.

5. **Check Customizations**: If you have custom code that integrates with this module, verify that it still works.

### Rollback Procedure

If the upgrade causes issues:

1. Restore your site from the backup created before the upgrade.

2. Revert to the previous version of the module.

3. Investigate the cause of the problem before attempting the upgrade again.

### Common Upgrade Issues

#### Database Schema Changes

Some upgrades may include database schema changes. The module will automatically handle these during the update process. Always backup your database before upgrading.

#### Configuration Changes

New versions may introduce new configuration options or change existing ones. Review your settings after upgrading.

#### Deprecation Warnings

New versions may deprecate certain APIs or functionality. Check the logs for any deprecation warnings after upgrading.

### Automated Upgrade

You can also use Drush for an automated upgrade:

```bash
# Download the latest version
drush dl event_registration

# Run database updates
drush updb

# Clear cache
drush cr
```

### Support

If you encounter issues during the upgrade process:

1. Check the issue queue on the project page
2. Consult the documentation
3. Reach out to the community for support
4. If it's a critical issue, contact the maintainers directly

Remember to always test upgrades in a development environment before applying them to production sites.