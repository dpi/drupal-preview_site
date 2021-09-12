<?php

namespace Drupal\preview_site\Deploy;

use Drupal\Core\Plugin\PluginBase;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\PreviewSitePluginTrait;

/**
 * Defines a base deploy plugin.
 */
abstract class DeployPluginBase extends PluginBase implements DeployPluginInterface {

  use PreviewSitePluginTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function decommissionPreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    // nil-op.
  }

  /**
   * {@inheritdoc}
   */
  public function deletePreviewSiteBuild(PreviewSiteBuildInterface $build): void {
    // nil-op.
  }

  /**
   * {@inheritdoc}
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string {
    return trim($this->getDeploymentBaseUri($build), '/') . '/' . $url;
  }

  /**
   * {@inheritdoc}
   */
  public function completeDeployment(PreviewSiteBuildInterface $build): void {
    // nil-op.
  }

}
