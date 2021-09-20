<?php

namespace Drupal\preview_site_s3\Plugin\PreviewSite\Deploy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\preview_site\Deploy\DeployPluginBase;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Generate\FileHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a plugin for deploying to S3.
 *
 * @PreviewSiteDeploy(
 *   id = "preview_site_s3",
 *   title = @Translation("S3"),
 *   description = @Translation("Deploy to S3."),
 * )
 */
class S3 extends DeployPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Client factory.
   *
   * @var \Drupal\s3client\S3ClientFactoryInterface
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->token = $container->get('token');
    $instance->clientFactory = $container->get('s3client.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bucket' => '',
      'key' => '',
      'secret' => '',
      'naming' => '[preview_site_build:uuid:value]',
      'domain' => '',
      'region' => '',
      'max_age' => 300,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      'bucket' => [
        '#type' => 'textfield',
        '#title' => $this->t('Bucket'),
        '#default_value' => $this->configuration['bucket'],
        '#required' => TRUE,
      ],
      'key' => [
        '#type' => 'textfield',
        '#title' => $this->t('Key'),
        '#default_value' => $this->configuration['key'],
        '#required' => TRUE,
      ],
      'secret' => [
        '#type' => 'textfield',
        '#title' => $this->t('Secret'),
        '#default_value' => $this->configuration['secret'],
        '#required' => TRUE,
      ],
      'region' => [
        '#type' => 'textfield',
        '#title' => $this->t('Region'),
        '#default_value' => $this->configuration['region'],
        '#required' => TRUE,
      ],
      'domain' => [
        '#type' => 'textfield',
        '#title' => $this->t('Domain'),
        '#default_value' => $this->configuration['domain'],
        '#required' => TRUE,
      ],
      'max_age' => [
        '#type' => 'number',
        '#title' => $this->t('Max age'),
        '#default_value' => $this->configuration['max_age'],
        '#required' => TRUE,
        '#min' => 0,
        '#max' => 86400,
        '#description' => $this->t('Set a max-age on files sent to S3. Used in the Cache-Control header with various request/response flows.'),
      ],
      'naming' => [
        '#type' => 'textfield',
        '#title' => $this->t('Naming'),
        '#default_value' => $this->configuration['naming'],
        '#description' => $this->t('Enter a naming convention for preview site builds. You may use <code>[preview_site_build:*]</code> tokens. The pattern entered here will be used for prefixing files when uploading to S3. Please note that this may not work with all generation plugins, as typically generation will assume a base-path of /, so you may need additional handling in e.g. Cloudfront or a Lambda to ensure that relative links work correctly.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $domain = $form_state->getValue('domain');
    if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
      $form_state->setErrorByName('domain', $this->t('@name is not a valid domain name.', [
        '@name' => $domain,
      ]));
    }
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => $domain]);
    try {
      $host = $request->getHost();
    }
    catch (SuspiciousOperationException $e) {
      $host = FALSE;
    }
    if (empty($host)) {
      $form_state->setErrorByName('domain', $this->t('@name does not match the trusted host name patterns for this server, static generation is unlikely to work. Edit your settings.php and add this domain to the trusted host patterns.', [
        '@name' => $domain,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['bucket'] = $form_state->getValue('bucket');
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['secret'] = $form_state->getValue('secret');
    $this->configuration['naming'] = trim($form_state->getValue('naming'), '/');
    $this->configuration['domain'] = $form_state->getValue('domain');
    $this->configuration['region'] = $form_state->getValue('region');
    $this->configuration['max_age'] = $form_state->getValue('max_age');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function deployArtifact(PreviewSiteBuildInterface $build, FileInterface $file): void {
    $client = $this->clientFactory->createClient($this->configuration['key'], $this->configuration['secret'], $this->configuration['region']);
    $file_name = sprintf('%s/%s', trim($this->token->replace($this->configuration['naming'], [
      'preview_site_build' => $build,
    ], ['clean' => TRUE]), '/'), FileHelper::getFilePathWithoutSchema($file, $build));
    $resource = fopen($file->getFileUri(), 'rb');
    try {
      $client->putObject([
        'Bucket' => $this->configuration['bucket'],
        'Key' => $file_name,
        'Body' => $resource,
        'CacheControl' => 'max-age=' . $this->configuration['max_age'],
      ]);
      $build->addLogEntry(sprintf('Pushed %s to %s', $file->getFilename(), $file_name));
    }
    catch (\Throwable $e) {
      $build->addLogEntry(sprintf('ERROR: Could not deploy %s: %s', $file->getFileUri(), $e->getMessage()));
    }
    finally {
      if (is_resource($resource)) {
        fclose($resource);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeploymentBaseUri(PreviewSiteBuildInterface $build): ?string {
    $token_data = [
      'preview_site_build' => $build,
    ];
    $token_options = [
      'clean' => TRUE,
    ];
    return sprintf(
      'https://%s/%s/',
      $this->token->replace($this->configuration['domain'], $token_data, $token_options),
      $this->token->replace($this->configuration['naming'], $token_data, $token_options)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function decommissionPreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::decommissionPreviewSiteBuild($build);
    $this->doDeleteArtifactsFromS3($build);
  }

  /**
   * {@inheritdoc}
   */
  public function deletePreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::deletePreviewSiteBuild($build);
    $this->doDeleteArtifactsFromS3($build, FALSE);
  }

  /**
   * Deletes items from S3.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Items to delete from.
   * @param bool $with_log
   *   TRUE to log operations. If the build is itself being deleted, pass FALSE.
   */
  protected function doDeleteArtifactsFromS3(PreviewSiteBuildInterface $build, $with_log = TRUE): void {
    $client = $this->clientFactory->createClient($this->configuration['key'], $this->configuration['secret'], $this->configuration['region']);
    $objects = [];
    $prefix = trim($this->token->replace($this->configuration['naming'], [
      'preview_site_build' => $build,
    ], ['clean' => TRUE]), '/');

    try {
      $response = $client->getIterator('ListObjects', [
        'Bucket' => $this->configuration['bucket'],
        'Prefix' => $prefix,
      ]);
      foreach ($response as $object) {
        $objects[] = ['Key' => $object['Key']];
      }
      $client->deleteObjects([
        'Bucket' => $this->configuration['bucket'],
        'Delete' => ['Objects' => $objects],
      ]);
      if ($with_log) {
        $build->addLogEntry(sprintf('Delete items %s', implode(', ', array_column($objects, 'Key'))));
      }
    }
    catch (\Throwable $e) {
      if ($with_log) {
        $build->addLogEntry(sprintf('Could not delete items %s', implode(', ', array_column($objects, 'Key'))));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string {
    $token_data = [
      'preview_site_build' => $build,
    ];
    $token_options = [
      'clean' => TRUE,
    ];
    return sprintf(
      'https://%s/%s',
      $this->token->replace($this->configuration['domain'], $token_data, $token_options),
      trim($url, '/')
    );
  }

}
