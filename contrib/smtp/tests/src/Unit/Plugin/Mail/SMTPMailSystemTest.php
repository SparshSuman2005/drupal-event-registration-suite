<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit\Plugin\Mail;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\smtp\Plugin\Mail\SMTPMailSystem;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Validate requirements for SMTPMailSystem.
 *
 * @group SMTP
 */
class SMTPMailSystemTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The mock config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactory;

  /**
   * The mock config factory rerouted.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactoryRerouted;

  /**
   * The mock logger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockLogger;

  /**
   * The mock messenger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockMessenger;

  /**
   * The mock current user.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockCurrentUser;

  /**
   * The mock file system.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockFileSystem;

  /**
   * The mock mime type guesser.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mimeTypeGuesser;

  /**
   * The mock render.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockRender;

  /**
   * The mock session.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockSession;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->mockConfigFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_timeout' => 30,
        'smtp_reroute_address' => '',
      ],
      'smtp.advanced' => [
        'enabled' => FALSE,
      ],
      'system.site' => ['name' => 'Mock site name', 'mail' => 'noreply@testmock.mock'],
    ]);
    $this->mockConfigFactoryRerouted = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_reroute_address' => 'blackhole@galaxy.com',
      ],
      'smtp.advanced' => [
        'enabled' => FALSE,
      ],
    ]);

    $this->mockLogger = $this->prophesize(LoggerChannelInterface::class);
    $this->mockMessenger = $this->prophesize(MessengerInterface::class);
    $this->mockCurrentUser = $this->prophesize(AccountProxy::class);
    $this->mockFileSystem = $this->prophesize(FileSystem::class);
    $this->mimeTypeGuesser = $this->prophesize(MimeTypeGuesser::class);
    $this->mockRender = $this->prophesize(RendererInterface::class);
    $this->mockSession = $this->prophesize(SessionInterface::class);

    $container->set('config.factory', $this->mockConfigFactory);
    $container->set('logger.factory', $this->mockLogger->reveal());
    $container->set('messenger', $this->mockMessenger->reveal());
    $container->set('current_user', $this->mockCurrentUser->reveal());
    $container->set('file_system', $this->mockFileSystem->reveal());
    $container->set('file.mime_type.guesser', $this->mimeTypeGuesser->reveal());
    $container->set('renderer', $this->mockRender->reveal());
    $container->set('session', $this->mockSession->reveal());

    $container->set('string_translation', $this->getStringTranslationStub());

    // Email validator.
    $this->emailValidator = new EmailValidator();
    $container->set('email.validator', $this->emailValidator);
    \Drupal::setContainer($container);
  }

  /**
   * Provides scenarios for getComponents().
   */
  public static function getComponentsProvider() {
    return [
      [
        // Input.
        'name@example.com',
        // Expected.
        [
          'name' => '',
          'email' => 'name@example.com',
        ],
      ],
      [
        ' name@example.com',
        [
          'name' => '',
          'input' => 'name@example.com',
          'email' => 'name@example.com',
        ],
      ],
      [
        'name@example.com ',
        [
          'name' => '',
          'input' => 'name@example.com',
          'email' => 'name@example.com',
        ],
      ],
      [
        'some name <address@example.com>',
        [
          'name' => 'some name',
          'email' => 'address@example.com',
        ],
      ],
      [
        '"some name" <address@example.com>',
        [
          'name' => 'some name',
          'email' => 'address@example.com',
        ],
      ],
      [
        '<address@example.com>',
        [
          'name' => '',
          'email' => 'address@example.com',
        ],
      ],
    ];
  }

  /**
   * Test getComponents().
   *
   * @dataProvider getComponentsProvider
   */
  public function testGetComponents($input, $expected) {
    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $this->mockConfigFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal()
    );

    $ret = $mailSystem->publicGetComponents($input);

    if (!empty($expected['input'])) {
      $this->assertEquals($expected['input'], $ret['input']);
    }
    else {
      $this->assertEquals($input, $ret['input']);
    }

    $this->assertEquals($expected['name'], $ret['name']);
    $this->assertEquals($expected['email'], $ret['email']);
  }

  /**
   * Test applyRerouting().
   */
  public function testApplyRerouting() {
    $mailSystemRerouted = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $this->mockConfigFactoryRerouted,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );
    $to = 'abc@example.com';
    $headers = [
      'some' => 'header',
      'cc' => 'xyz@example.com',
      'bcc' => 'ttt@example.com',
    ];
    [$new_to, $new_headers] = $mailSystemRerouted->publicApplyRerouting($to, $headers);
    $this->assertEquals($new_to, 'blackhole@galaxy.com', 'to address is set to the reroute address.');
    $this->assertEquals($new_headers, ['some' => 'header'], 'bcc and cc headers are unset when rerouting.');

    $mailSystemNotRerouted = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $this->mockConfigFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );
    $to = 'abc@example.com';
    $headers = [
      'some' => 'header',
      'cc' => 'xyz@example.com',
      'bcc' => 'ttt@example.com',
    ];
    [$new_to, $new_headers] = $mailSystemNotRerouted->publicApplyRerouting($to, $headers);
    $this->assertEquals($new_to, $to, 'original to address is preserved when not rerouting.');
    $this->assertEquals($new_headers, $headers, 'bcc and cc headers are preserved when not rerouting.');
  }

  /**
   * Provides scenarios for testMailValidator().
   */
  public static function mailValidatorProvider() {
    $emailValidatorPhpMailerDefault = new EmailValidatorPhpMailerDefault();
    $emailValidatorDrupal = new EmailValidator();
    return [
      'Without umlauts, PHPMailer default validator, no exception' => [
        'test@drupal.org',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorPhpMailerDefault,
        NULL,
      ],
      'With umlauts in local part, PHPMailer default validator, exception' => [
        'testmüller@drupal.org',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorPhpMailerDefault,
        PHPMailerException::class,
      ],
      'With umlauts in domain part, PHPMailer default validator, exception' => [
        'test@müllertest.de',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorPhpMailerDefault,
        PHPMailerException::class,
      ],
      'Without top-level domain in domain part, PHPMailer default validator, exception' => [
        'test@drupal',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorPhpMailerDefault,
        PHPMailerException::class,
      ],
      'Without umlauts, Drupal mail validator, no exception' => [
        'test@drupal.org',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorDrupal,
        NULL,
      ],
      'With umlauts in local part, Drupal mail validator, no exception' => [
        'testmüller@drupal.org',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorDrupal,
        NULL,
      ],
      'With umlauts in domain part, Drupal mail validator, no exception' => [
        'test@müllertest.de',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorDrupal,
        NULL,
      ],
      'Without top-level domain in domain part, Drupal mail validator, no exception' => [
        'test@drupal',
        'PhpUnit Localhost <phpunit@localhost.com>',
        $emailValidatorDrupal,
        NULL,
      ],
    ];
  }

  /**
   * Test mail() with focus on the mail validator.
   *
   * @dataProvider mailValidatorProvider
   */
  public function testMailValidator(string $to, string $from, EmailValidatorInterface $validator, $exception) {
    $this->emailValidator = $validator;

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $validator,
      $this->mockConfigFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal()
    );
    $message = [
      'to' => $to,
      'from' => $from,
      'body' => 'Some test content for testMailValidatorDrupal',
      'headers' => [
        'content-type' => 'text/plain',
      ],
      'subject' => 'testMailValidatorDrupal',
    ];

    if (isset($exception)) {
      $this->expectException($exception);
    }
    // Call function.
    $result = $mailSystem->mail($message);

    // More important than the result is that no exception was thrown, if
    // $exception is unset.
    self::assertTrue($result);
  }

  /**
   * Test mail() with missing header value.
   */
  public function testMailHeader() {
    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $this->mockConfigFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    $message = [
      'to' => 'test@drupal.org',
      'from' => 'PhpUnit Localhost <phpunit@localhost.com>',
      'body' => 'Some test content for testMailHeaderDrupal',
      'headers' => [
        'content-type' => 'text/plain',
        'from' => 'test@drupal.org',
        'reply-to' => 'test@drupal.org',
        'cc' => '',
        'bcc' => '',
      ],
      'subject' => 'testMailHeaderDrupal',
    ];

    // Call function.
    $result = $mailSystem->mail($message);

    self::assertTrue($result);
  }

  /**
   * Tests #3308653 and duplicated headers.
   */
  public function testFromHeaders3308653() {
    $mailer = new class (
      [],
      'SMTPMailSystem',
      [],
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(MessengerInterface::class),
      new EmailValidator(),
      $this->getConfigFactoryStub([
        'smtp.settings' => [
          'smtp_timeout' => 30,
          'smtp_reroute_address' => '',
        ],
        'smtp.advanced' => [
          'enabled' => FALSE,
        ],
        'system.site' => ['name' => 'Mock site name', 'mail' => 'noreply@testmock.mock'],
      ]),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(MimeTypeGuesserInterface::class),
      $this->createMock(RendererInterface::class),
      $this->createMock(SessionInterface::class)
    ) extends SMTPMailSystem {

      /**
       * {@inheritdoc}
       */
      public function smtpMailerSend(array $mailerArr) {
        return $mailerArr;
      }

      /**
       * {@inheritdoc}
       */
      protected function getMailer() {
        return new class (TRUE) extends PHPMailer {

          /**
           * Return the MIME header for testing.
           *
           * @return array
           *   The MIMEHeader as an array.
           */
          //phpcs:ignore
          public function getMIMEHeaders() {
            return array_filter(explode(static::$LE, $this->MIMEHeader));
          }

        };
      }

    };

    // Message as prepared by \Drupal\Core\Mail\MailManager::doMail().
    $message = [
      'id' => 'smtp_test',
      'module' => 'smtp',
      'key' => 'test',
      'to' => 'test@drupal.org',
      'from' => 'phpunit@localhost.com',
      'reply-to' => 'phpunit@localhost.com',
      'langcode' => 'en',
      'params' => [],
      'send' => TRUE,
      'subject' => 'testMailHeaderDrupal',
      'body' => ['Some test content for testMailHeaderDrupal'],
    ];
    $headers = [
      'MIME-Version' => '1.0',
      'Content-Type' => 'text/plain; charset=UTF-8; format=flowed; delsp=yes',
      'Content-Transfer-Encoding' => '8Bit',
      'X-Mailer' => 'Drupal',
    ];
    $headers['From'] = $headers['Sender'] = $headers['Return-Path'] = $message['from'];
    $message['headers'] = $headers;

    // Prevent passing `null` to preg_quote in
    // \Drupal\Core\Mail\MailFormatHelper::htmlToMailUrls().
    $GLOBALS['base_path'] = '/';
    $message = $mailer->format($message);
    $result = $mailer->mail($message);

    self::assertArrayHasKey('to', $result);
    self::assertEquals($message['to'], $result['to']);
    self::assertArrayHasKey('from', $result);
    self::assertEquals($message['from'], $result['from']);
    self::assertArrayHasKey('mailer', $result);
    $phpmailer = $result['mailer'];
    self::assertInstanceOf(PHPMailer::class, $phpmailer);
    // Pre-send constructs the email message.
    self::assertTrue($phpmailer->preSend());

    $mime_headers = [];
    foreach ($phpmailer->getMIMEHeaders() as $header) {
      [$name, $value] = explode(': ', $header, 2);
      self::assertArrayNotHasKey(strtolower($name), $mime_headers);
      $mime_headers[strtolower($name)] = $value;
    }
  }

  /**
   * Test sanitizeDebugMessage() with AUTH PLAIN commands.
   */
  public function testSanitizeDebugMessageAuthPlain(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secretpassword123',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    // Test AUTH PLAIN with base64 encoded credentials.
    $message = 'AUTH PLAIN dXNlckBleGFtcGxlLmNvbQBzZWNyZXRwYXNzd29yZDEyMw==';
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertEquals('AUTH PLAIN [REDACTED]', $sanitized, 'AUTH PLAIN command should be masked.');
  }

  /**
   * Test sanitizeDebugMessage() with AUTH LOGIN commands.
   */
  public function testSanitizeDebugMessageAuthLogin(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secretpassword123',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    // Test AUTH LOGIN with base64 encoded username:password.
    $base64Credentials = base64_encode('user@example.com:secretpassword123');
    $message = "AUTH LOGIN $base64Credentials";
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertStringContainsString('[REDACTED]', $sanitized, 'AUTH LOGIN credentials should be masked.');
    $this->assertStringNotContainsString($base64Credentials, $sanitized, 'Base64 credentials should not appear in sanitized message.');
  }

  /**
   * Test sanitizeDebugMessage() with plain text password.
   */
  public function testSanitizeDebugMessagePlainTextPassword(): void {
    $password = 'mySecretPassword123';
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => 'user@example.com',
        'smtp_password' => $password,
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    $message = "Connection established with password: $password";
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertStringContainsString('[REDACTED]', $sanitized, 'Plain text password should be masked.');
    $this->assertStringNotContainsString($password, $sanitized, 'Password should not appear in sanitized message.');
  }

  /**
   * Test sanitizeDebugMessage() with password patterns.
   */
  public function testSanitizeDebugMessagePasswordPatterns(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secret123',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    $testCases = [
      'password: testpass123' => 'password: [REDACTED]',
      'Password = testpass123' => 'password: [REDACTED]',
      'PASSWORD: secret' => 'password: [REDACTED]',
      'pass: secret' => 'pass: [REDACTED]',
      'Pass = secret' => 'pass: [REDACTED]',
    ];

    foreach ($testCases as $input => $expected) {
      $sanitized = $mailSystem->publicSanitizeDebugMessage($input);
      $this->assertEquals($expected, $sanitized, "Password pattern '$input' should be masked.");
    }
  }

  /**
   * Test sanitizeDebugMessage() with username masking.
   */
  public function testSanitizeDebugMessageUsernameMasking(): void {
    $username = 'testuser@example.com';
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => $username,
        'smtp_password' => 'secret123',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    $message = "Authenticating as $username";
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertStringContainsString('[REDACTED]', $sanitized, 'Username should be masked.');
    $this->assertStringNotContainsString($username, $sanitized, 'Username should not appear in sanitized message.');
  }

  /**
   * Test sanitizeDebugMessage() with SMTP response code 334.
   */
  public function testSanitizeDebugMessageResponseCode334(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secret123',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    $base64Response = base64_encode('challenge-string');
    $message = "334 $base64Response";
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertEquals('334 [REDACTED]', $sanitized, 'SMTP response code 334 should be masked.');
    $this->assertStringNotContainsString($base64Response, $sanitized, 'Base64 response should not appear.');
  }

  /**
   * Test sanitizeDebugMessage() with no credentials configured.
   */
  public function testSanitizeDebugMessageNoCredentials(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => '',
        'smtp_password' => '',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    // AUTH PLAIN should still be masked even without credentials.
    $message = 'AUTH PLAIN dXNlckBleGFtcGxlLmNvbQBzZWNyZXRwYXNzd29yZDEyMw==';
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertEquals('AUTH PLAIN [REDACTED]', $sanitized, 'AUTH PLAIN should be masked even without credentials.');

    // Password patterns should still be masked even without password config.
    $testCases = [
      'password: testpass123' => 'password: [REDACTED]',
      'Password = testpass123' => 'password: [REDACTED]',
      'pass: secret' => 'pass: [REDACTED]',
    ];

    foreach ($testCases as $input => $expected) {
      $sanitized = $mailSystem->publicSanitizeDebugMessage($input);
      $this->assertEquals($expected, $sanitized, "Password pattern '$input' should be masked even without password config.");
    }
  }

  /**
   * Test sanitizeDebugMessage() with complex SMTP debug output.
   */
  public function testSanitizeDebugMessageComplexOutput(): void {
    $username = 'testuser@example.com';
    $password = 'SuperSecret123!';
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => $username,
        'smtp_password' => $password,
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    // Simulate a complex SMTP debug output.
    $base64Auth = base64_encode("$username:$password");
    $message = <<<EOT
SMTP -> FROM SERVER: 250 OK
SMTP -> FROM SERVER: 334 VXNlcm5hbWU6
SMTP -> AUTH LOGIN $base64Auth
SMTP -> FROM SERVER: 334 UGFzc3dvcmQ6
Authenticating user: $username with password: $password
AUTH PLAIN $base64Auth
EOT;

    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);

    // Verify all sensitive data is masked.
    $this->assertStringNotContainsString($password, $sanitized, 'Password should not appear in complex output.');
    $this->assertStringNotContainsString($base64Auth, $sanitized, 'Base64 credentials should not appear.');
    $this->assertStringNotContainsString($username, $sanitized, 'Username should not appear.');
    $this->assertStringContainsString('[REDACTED]', $sanitized, 'Should contain redacted markers.');
    $this->assertStringContainsString('AUTH PLAIN [REDACTED]', $sanitized, 'AUTH PLAIN should be masked.');
  }

  /**
   * Test sanitize with non-credential base64 that should not be masked.
   */
  public function testSanitizeDebugMessageNonCredentialBase64(): void {
    $configFactory = $this->getConfigFactoryStub([
      'smtp.settings' => [
        'smtp_username' => '',
        'smtp_password' => '',
      ],
    ]);

    $mailSystem = new SMTPMailSystemTestHelper(
      [],
      '',
      [],
      $this->mockLogger->reveal(),
      $this->mockMessenger->reveal(),
      $this->emailValidator,
      $configFactory,
      $this->mockCurrentUser->reveal(),
      $this->mockFileSystem->reveal(),
      $this->mimeTypeGuesser->reveal(),
      $this->mockRender->reveal(),
      $this->mockSession->reveal(),
    );

    // Short base64 strings or ones that don't decode to credentials should
    // pass through.
    // Too short to trigger callback.
    $message = 'AUTH PLAIN AbCdEf123456';
    $sanitized = $mailSystem->publicSanitizeDebugMessage($message);
    $this->assertEquals('AUTH PLAIN [REDACTED]', $sanitized, 'AUTH PLAIN should always be masked regardless of length.');

    // Base64 that doesn't decode to colon-separated credentials.
    $base64Short = base64_encode('short');
    $message2 = "AUTH CUSTOM $base64Short";
    $sanitized2 = $mailSystem->publicSanitizeDebugMessage($message2);
    // Should remain unchanged if it doesn't decode to credentials.
    $this->assertStringNotContainsString('[REDACTED]', $sanitized2, 'Short or non-credential base64 should not be masked by callback.');
  }

}

/**
 * Test helper for SMTPMailSystemTest.
 */
class SMTPMailSystemTestHelper extends SMTPMailSystem {

  /**
   * Exposes getComponents for testing.
   */
  public function publicGetComponents($input) {
    return $this->getComponents($input);
  }

  /**
   * Dummy of smtpMailerSend.
   */
  public function smtpMailerSend($mailerArr) {
    return TRUE;
  }

  /**
   * Exposes applyRerouting() for testing.
   */
  public function publicApplyRerouting($to, array $headers) {
    return $this->applyRerouting($to, $headers);
  }

  /**
   * Exposes sanitizeDebugMessage() for testing.
   */
  public function publicSanitizeDebugMessage($message) {
    return $this->sanitizeDebugMessage($message);
  }

}

/**
 * An adaptor class wrapping the default PHPMailer validator.
 */
class EmailValidatorPhpMailerDefault implements EmailValidatorInterface {

  /**
   * {@inheritdoc}
   *
   * This function validates in same way the PHPMailer class does in its
   * default behavior.
   */
  public function isValid($email) {
    PHPMailer::$validator = 'php';
    return PHPMailer::validateAddress($email);
  }

}
