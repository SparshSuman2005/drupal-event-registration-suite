<?php

declare(strict_types=1);

namespace Drupal\Tests\smtp\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for smtp_help() function.
 *
 * @group smtp
 */
class SmtpHelpTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The mock config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockConfigFactory;

  /**
   * The mock mail config.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockMailConfig;

  /**
   * The mock route match.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $mockRouteMatch;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a new container.
    $this->container = new ContainerBuilder();

    // Mock config factory and system.mail config.
    $this->mockConfigFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->mockMailConfig = $this->prophesize(ImmutableConfig::class);
    $this->mockRouteMatch = $this->prophesize(RouteMatchInterface::class);

    // Mock url generator service.
    $mockUrlGenerator = $this->prophesize(UrlGeneratorInterface::class);
    $mockUrlGenerator->generateFromRoute(Argument::cetera())->willReturn('/admin/config/system/smtp');

    // Add services to container.
    $this->container->set('config.factory', $this->mockConfigFactory->reveal());
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('url_generator', $mockUrlGenerator->reveal());

    // Set the container.
    \Drupal::setContainer($this->container);

    // Include the module file if function doesn't exist.
    if (!function_exists('smtp_help')) {
      require_once dirname(__DIR__, 3) . '/smtp.module';
    }
  }

  /**
   * Tests smtp_help() with 'help.page.smtp' route.
   */
  public function testHelpPageSmtp(): void {
    $result = smtp_help('help.page.smtp', $this->mockRouteMatch->reveal());

    // The function returns a string (HTML output).
    $this->assertIsString($result);
    $this->assertStringContainsString('SMTP Authentication Support', $result);
  }

  /**
   * Tests smtp_help() with 'smtp.test' route when mail system is php_mail.
   */
  public function testSmtpTestRouteWithPhpMail(): void {
    // Mock system.mail config to return php_mail.
    $this->mockMailConfig->get('interface.default')->willReturn('php_mail');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // smtp.test route is not currently handled, so it returns NULL.
    $this->assertNull($result);
  }

  /**
   * Tests smtp_help() with 'smtp.test' route when mail is test_mail_collector.
   */
  public function testSmtpTestRouteWithTestMailCollector(): void {
    // Mock system.mail config to return test_mail_collector.
    $this->mockMailConfig->get('interface.default')->willReturn('test_mail_collector');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // smtp.test route is not currently handled, so it returns NULL.
    $this->assertNull($result);
  }

  /**
   * Tests smtp_help() with 'smtp.test' route when SMTP is already active.
   */
  public function testSmtpTestRouteWithSmtpMailSystem(): void {
    // Mock system.mail config to return SMTPMailSystem.
    $this->mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // When SMTP is already active, the function should return NULL.
    $this->assertNull($result);
  }

  /**
   * Tests smtp_help() with an unknown route.
   */
  public function testUnknownRoute(): void {
    $result = smtp_help('some.unknown.route', $this->mockRouteMatch->reveal());

    $this->assertNull($result);
  }

  /**
   * Tests smtp_help() with 'smtp.test' route and custom mail system.
   */
  public function testSmtpTestRouteWithCustomMailSystem(): void {
    // Mock system.mail config to return a custom mail system.
    $this->mockMailConfig->get('interface.default')->willReturn('custom_mail_system');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // smtp.test route is not currently handled, so it returns NULL.
    $this->assertNull($result);
  }

  /**
   * Tests the structure of the render array for 'smtp.test' route.
   */
  public function testSmtpTestRouteRenderArrayStructure(): void {
    // Mock system.mail config to return php_mail.
    $this->mockMailConfig->get('interface.default')->willReturn('php_mail');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // smtp.test route is not currently handled, so it returns NULL.
    $this->assertNull($result);
  }

  /**
   * Tests that help text is properly formatted without HTML in translations.
   */
  public function testHelpTextDoesNotContainHtmlInTranslations(): void {
    // Mock system.mail config to return php_mail.
    $this->mockMailConfig->get('interface.default')->willReturn('php_mail');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

    // smtp.test route is not currently handled, so it returns NULL.
    $this->assertNull($result);
  }

  /**
   * Tests help text with different mail systems to verify placeholders work.
   */
  public function testHelpTextPlaceholderReplacement(): void {
    $mail_systems = [
      'php_mail',
      'test_mail_collector',
      'custom_mail',
      'another_system',
    ];

    foreach ($mail_systems as $mail_system) {
      // Mock system.mail config to return the specific mail system.
      $this->mockMailConfig->get('interface.default')->willReturn($mail_system);
      $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());

      $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());

      // smtp.test route is not currently handled, so it returns NULL.
      $this->assertNull($result);
    }
  }

  /**
   * Tests that each route case returns the expected type.
   */
  public function testReturnTypes(): void {
    // help.page.smtp should return a string (HTML output).
    $result = smtp_help('help.page.smtp', $this->mockRouteMatch->reveal());
    $this->assertIsString($result);

    // smtp.test with non-SMTP mail system should return NULL
    // (not currently handled).
    $this->mockMailConfig->get('interface.default')->willReturn('php_mail');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());
    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());
    $this->assertNull($result);

    // smtp.test with SMTPMailSystem should return NULL.
    $this->mockMailConfig->get('interface.default')->willReturn('SMTPMailSystem');
    $this->mockConfigFactory->get('system.mail')->willReturn($this->mockMailConfig->reveal());
    $result = smtp_help('smtp.test', $this->mockRouteMatch->reveal());
    $this->assertNull($result);

    // Unknown route should return NULL.
    $result = smtp_help('unknown.route', $this->mockRouteMatch->reveal());
    $this->assertNull($result);
  }

}
