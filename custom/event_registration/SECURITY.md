# Security Policy

## Supported Versions

The Event Registration Module for Drupal 10 follows the same support cycle as Drupal core. Only the latest version of the module receives security updates.

| Version | Drupal Core | Supported          |
| ------- | ----------- | ------------------ |
| 1.x     | 10.x        | ✅ Yes             |
| < 1.0   | < 10.0      | ❌ No              |

## Reporting a Vulnerability

If you discover a security vulnerability in the Event Registration Module, please follow these steps:

### Private Disclosure
- **Do not** create a public issue in the GitHub repository
- **Do not** disclose the vulnerability publicly until it has been addressed
- Contact the maintainers directly through the Drupal security team

### How to Report
1. Visit the [Drupal Security Team](https://www.drupal.org/security-team) page
2. Follow the instructions for reporting security issues
3. Include the following information in your report:
   - Type of vulnerability (e.g., XSS, SQL injection, CSRF)
   - Location of the vulnerability (file and line number if possible)
   - Steps to reproduce the vulnerability
   - Potential impact of the vulnerability
   - Suggested remediation if known

### Response Timeline
- Acknowledgment of your report within 72 hours
- Regular updates on the status of the investigation
- Notification when the vulnerability is fixed
- Public disclosure after the fix is released

## Security Best Practices

### For Administrators
- Keep the module updated to the latest version
- Regularly review user permissions and access controls
- Monitor logs for suspicious activity
- Ensure your Drupal installation follows security best practices
- Use strong passwords and two-factor authentication where possible

### For Developers
- Validate and sanitize all user inputs
- Use Drupal's form API and database abstraction layer
- Follow Drupal coding standards
- Implement proper access controls
- Use HTTPS for all forms and administrative areas

## Known Security Measures

The Event Registration Module implements the following security measures:

- Input sanitization and validation
- SQL injection prevention through prepared statements
- Cross-site scripting (XSS) protection
- Cross-site request forgery (CSRF) tokens
- Access control through Drupal permissions
- Email validation and sanitization
- Rate limiting for form submissions
- Session management integration with Drupal core

## Security Updates

Security updates are released as soon as vulnerabilities are identified and fixed. Users are strongly encouraged to:

- Subscribe to security advisories
- Update the module promptly when security releases are announced
- Test updates in a staging environment before applying to production
- Maintain regular backups before applying updates

For more information about Drupal security, visit the [Drupal Security](https://www.drupal.org/security) page.