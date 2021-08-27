<?php

namespace Drupal\preview_site\Deploy;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;

/**
 * Defines an interface for preview site deploy plugins.
 */
interface DeployPluginInterface extends ConfigurableInterface, PluginInspectionInterface, PluginWithFormsInterface, PluginFormInterface, DependentPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Gets the plugin title.
   *
   * @return string
   *   Plugin title.
   */
  public function getTitle() : string;

  /**
   * Deploys an artifact.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Preview site to build.
   * @param \Drupal\file\FileInterface $file
   *   File to deploy.
   */
  public function deployArtifact(PreviewSiteBuildInterface $build, FileInterface $file) : void;

  /**
   * Returns the base URI to the deployment.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Preview site build.
   *
   * @return string|null
   *   Base URI to the deployment.
   */
  public function getDeploymentBaseUri(PreviewSiteBuildInterface $build) : ?string;

  /**
   * Decommissions a preview site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build being decommissioned.
   */
  public function decommissionPreviewSiteBuild(PreviewSiteBuildInterface $build) : void;

  /**
   * Deletes a preview site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build being deleted.
   */
  public function deletePreviewSiteBuild(PreviewSiteBuildInterface $build) : void;

}
