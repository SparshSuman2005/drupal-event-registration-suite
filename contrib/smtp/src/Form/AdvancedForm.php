<?php

declare(strict_types=1);

namespace Drupal\smtp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Implements the SMTP advanced settings form.
 */
final class AdvancedForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smtp_advanced_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('smtp.advanced');

    $enabled_overridden = $config->hasOverrides('enabled');
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable SMTP advanced settings'),
      '#default_value' => $config->get('enabled'),
      '#disabled' => $enabled_overridden,
      '#description' => $enabled_overridden ? $this->t('(Overridden) Enable SMTP advanced settings') : $this->t('Enable SMTP advanced settings'),
    ];

    $form['ssl'] = [
      '#type' => 'details',
      '#title' => $this->t('SSL'),
      '#description' => $this->t('More information can be found at @link.', ['@link' => Link::fromTextAndUrl($this->t('PHP documentation'), Url::fromUri('https://www.php.net/manual/en/context.ssl.php'))->toString()]),
      '#open' => TRUE,
    ];

    $ssl_peer_name_overridden = $config->hasOverrides('ssl__peer_name');
    $form['ssl']['ssl__peer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Peer name'),
      '#default_value' => $config->get('ssl__peer_name'),
      '#disabled' => $ssl_peer_name_overridden,
      '#description' => $ssl_peer_name_overridden ? $this->t('(Overridden) Peer name to be used. If this value is not set, then the name is guessed based on the hostname used when opening the stream.') : $this->t('Peer name to be used. If this value is not set, then the name is guessed based on the hostname used when opening the stream.'),
    ];
    $form['ssl']['ssl__verify_peer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify peer'),
      '#description' => $this->t('Require verification of SSL certificate used.'),
      '#default_value' => $config->get('ssl__verify_peer'),
      '#disabled' => $config->hasOverrides('ssl__verify_peer'),
    ];
    $form['ssl']['ssl__verify_peer_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify peer name'),
      '#description' => $this->t('Require verification of peer name.'),
      '#default_value' => $config->get('ssl__verify_peer_name'),
      '#disabled' => $config->hasOverrides('ssl__verify_peer_name'),
    ];
    $form['ssl']['ssl__allow_self_signed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow self signed'),
      '#description' => $this->t('Allow self-signed certificates. Requires verify_peer.'),
      '#default_value' => $config->get('ssl__allow_self_signed'),
      '#disabled' => $config->hasOverrides('ssl__allow_self_signed'),
    ];
    $form['ssl']['ssl__cafile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CA file'),
      '#description' => $this->t('Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer.'),
      '#default_value' => $config->get('ssl__cafile'),
      '#disabled' => $config->hasOverrides('ssl__cafile'),
    ];
    $form['ssl']['ssl__capath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CA path'),
      '#description' => $this->t('If cafile is not specified or if the certificate is not found there, the directory pointed to by capath is searched for a suitable certificate. capath must be a correctly hashed certificate directory.'),
      '#default_value' => $config->get('ssl__capath'),
      '#disabled' => $config->hasOverrides('ssl__capath'),
    ];
    $form['ssl']['ssl__local_cert'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local certificate'),
      '#description' => $this->t('Path to local certificate file on filesystem. It must be a PEM encoded file which contains your certificate and private key. It can optionally contain the certificate chain of issuers. The private key also may be contained in a separate file specified by local_pk.'),
      '#default_value' => $config->get('ssl__local_cert'),
      '#disabled' => $config->hasOverrides('ssl__local_cert'),
    ];
    $form['ssl']['ssl__local_pk'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local private key'),
      '#description' => $this->t('Path to local private key file on filesystem in case of separate files for certificate (local_cert) and private key.'),
      '#default_value' => $config->get('ssl__local_pk'),
      '#disabled' => $config->hasOverrides('ssl__local_pk'),
    ];
    $form['ssl']['ssl__passphrase'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Passphrase'),
      '#description' => $this->t('Passphrase with which your local_cert file was encoded.'),
      '#default_value' => $config->get('ssl__passphrase'),
      '#disabled' => $config->hasOverrides('ssl__passphrase'),
    ];
    $form['ssl']['ssl__verify_depth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verify depth'),
      '#description' => $this->t('Abort if the certificate chain is too deep.'),
      '#default_value' => $config->get('ssl__verify_depth'),
      '#disabled' => $config->hasOverrides('ssl__verify_depth'),
    ];
    $form['ssl']['ssl__SNI_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('SNI enabled'),
      '#description' => $this->t('If set to true server name indication will be enabled. Enabling SNI allows multiple certificates on the same IP address.'),
      '#default_value' => $config->get('ssl__SNI_enabled'),
      '#disabled' => $config->hasOverrides('ssl__SNI_enabled'),
    ];

    $form['socket'] = [
      '#type' => 'details',
      '#title' => $this->t('Socket'),
      '#open' => TRUE,
      '#description' => $this->t('More information can be found at @link.', ['@link' => Link::fromTextAndUrl($this->t('PHP documentation'), Url::fromUri('https://www.php.net/manual/en/context.socket.php'))->toString()]),
    ];

    $form['socket']['socket__bindto'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Socket bind to'),
      '#description' => $this->t('Used to specify the IP address (either IPv4 or IPv6) and/or the port number that PHP will use to access the network. The syntax is ip:port for IPv4 addresses, and [ip]:port for IPv6 addresses. Setting the IP or the port to 0 will let the system choose the IP and/or port.'),
      '#default_value' => $config->get('socket__bindto'),
      '#disabled' => $config->hasOverrides('socket__bindto'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('smtp.advanced')
      ->set('enabled', $values['enabled'])
      ->set('socket__bindto', $values['socket__bindto'])
      ->set('ssl__peer_name', $values['ssl__peer_name'])
      ->set('ssl__verify_peer', $values['ssl__verify_peer'])
      ->set('ssl__verify_peer_name', $values['ssl__verify_peer_name'])
      ->set('ssl__allow_self_signed', $values['ssl__allow_self_signed'])
      ->set('ssl__cafile', $values['ssl__cafile'])
      ->set('ssl__capath', $values['ssl__capath'])
      ->set('ssl__local_cert', $values['ssl__local_cert'])
      ->set('ssl__local_pk', $values['ssl__local_pk'])
      ->set('ssl__passphrase', $values['ssl__passphrase'])
      ->set('ssl__verify_depth', $values['ssl__verify_depth'])
      ->set('ssl__SNI_enabled', $values['ssl__SNI_enabled'])
      ->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['smtp.advanced'];
  }

}
