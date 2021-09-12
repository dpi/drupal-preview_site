<?php

namespace Drupal\preview_site\Deploy;

use Drupal\file\FileInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\PreviewSitePluginInterface;

/**
 * Defines an interface for preview site deploy plugins.
 */
interface DeployPluginInterface extends PreviewSitePluginInterface {

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

  /**
   * Completes a deployment.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   */
  public function completeDeployment(PreviewSiteBuildInterface $build): void;

}
