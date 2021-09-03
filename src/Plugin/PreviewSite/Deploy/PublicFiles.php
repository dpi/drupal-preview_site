<?php

declare(strict_types=1);

namespace Drupal\preview_site\Plugin\PreviewSite\Deploy;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\preview_site\Deploy\DeployPluginBase;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Generate\FileHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a deploy plugin that uses public files.
 *
 * @PreviewSiteDeploy(
 *   id = "preview_site_public",
 *   title = @Translation("Public files"),
 *   description = @Translation("Deploy to the public files folder."),
 * )
 */
class PublicFiles extends DeployPluginBase {

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['naming' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      'naming' => [
        '#type' => 'textfield',
        '#title' => $this->t('Naming'),
        '#default_value' => $this->configuration['naming'],
        '#description' => $this->t('Enter a naming convention for preview site builds. You may use <code>[preview_site_build:*]</code> tokens. The pattern entered here will be used for creating a folder in the public file system.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['naming'] = $form_state->getValue('naming');
  }

  /**
   * {@inheritdoc}
   */
  public function deployArtifact(PreviewSiteBuildInterface $build, FileInterface $file): void {
    $destination = sprintf('public://%s/%s', trim($this->token->replace($this->configuration['naming'], [
      'preview_site_build' => $build,
    ], ['clean' => TRUE]), '/'), FileHelper::getFilePathWithoutSchema($file, $build));
    $directory = dirname($destination);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->copy($file->getFileUri(), $destination, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeploymentBaseUri(PreviewSiteBuildInterface $build): ?string {
    $destination = sprintf('public://%s', trim($this->token->replace($this->configuration['naming'], [
      'preview_site_build' => $build,
    ], ['clean' => TRUE]), '/'));
    return file_create_url($destination);
  }

  /**
   * Do cleanup of files from public storage.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   */
  private function doCleanUp(PreviewSiteBuildInterface $build): void {
    foreach ($build->getArtifacts() as $file) {
      $destination = sprintf('public://%s/%s', trim($this->token->replace($this->configuration['naming'], [
        'preview_site_build' => $build,
      ], ['clean' => TRUE]), '/'), FileHelper::getFilePathWithoutSchema($file, $build));
      $this->fileSystem->unlink($destination);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function decommissionPreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::decommissionPreviewSiteBuild($build);
    $this->doCleanUp($build);
  }

  /**
   * {@inheritdoc}
   */
  public function deletePreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    parent::deletePreviewSiteBuild($build);
    $this->doCleanUp($build);
  }

}
