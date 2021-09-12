<?php

namespace Drupal\preview_site_test\Plugin\PreviewSite\Deploy;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\preview_site\Deploy\DeployPluginBase;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a test deploy plugin.
 *
 * @PreviewSiteDeploy(
 *   id = "test",
 *   title = @Translation("Test"),
 *   description = @Translation("Test deploy plugin."),
 * )
 *
 * @codeCoverageIgnore
 */
class TestDeploy extends DeployPluginBase {

  const DECOMISSION_STEP = 'preview_site_test_deploy_decomission';
  const DELETE_STEP = 'preview_site_test_deploy_delete';

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['prefix' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      'prefix' => [
        '#type' => 'textfield',
        '#title' => $this->t('Prefix'),
        '#default_value' => $this->configuration['prefix'],
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['prefix'] = $form_state->getValue('prefix');
  }

  /**
   * {@inheritdoc}
   */
  public function deployArtifact(PreviewSiteBuildInterface $build, FileInterface $file): void {
    $destination = 'public://preview-site-test/' . $this->configuration['prefix'] . '/' . ltrim(parse_url($file->getFileUri(), PHP_URL_PATH), '/');
    $directory = dirname($destination);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->copy($file->getFileUri(), $destination, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function completeDeployment(PreviewSiteBuildInterface $build): void {
    $file_uris = [];
    foreach ($build->get('artifacts') as $item) {
      if ($file = $item->entity) {
        assert($file instanceof FileInterface);
        $file_uris[] = $file->getFileUri();
      }
    }
    $this->state->set(sprintf('preview_site_build_files:%s', $build->uuid()), $file_uris);
    parent::completeDeployment($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeploymentBaseUri(PreviewSiteBuildInterface $build): ?string {
    return sprintf('https://example.com/%s/', $this->configuration['prefix']);
  }

  /**
   * {@inheritdoc}
   */
  public function decommissionPreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::decommissionPreviewSiteBuild($build);
    $this->state->set(self::DECOMISSION_STEP, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function deletePreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::deletePreviewSiteBuild($build);
    $this->state->set(self::DELETE_STEP, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string {
    return $this->getDeploymentBaseUri($build) . $url;
  }

}
