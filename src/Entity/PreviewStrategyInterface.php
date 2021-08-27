<?php

namespace Drupal\preview_site\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\preview_site\Deploy\DeployPluginInterface;
use Drupal\preview_site\Generate\GeneratePluginInterface;

/**
 * Defines an interface for preview strategy content entity.
 */
interface PreviewStrategyInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets the deploy plugin instance.
   *
   * @return \Drupal\preview_site\Deploy\DeployPluginInterface|null
   *   Deploy plugin instance.
   */
  public function getDeployPlugin(): ?DeployPluginInterface;

  /**
   * Gets the generate plugin instance.
   *
   * @return \Drupal\preview_site\Generate\GeneratePluginInterface|null
   *   Generate plugin instance.
   */
  public function getGeneratePlugin(): ?GeneratePluginInterface;

  /**
   * Gets the deploy plugin ID.
   *
   * @return string
   *   Plugin ID.
   */
  public function getDeployPluginId(): ?string;

  /**
   * Gets the generate plugin ID.
   *
   * @return string
   *   Plugin ID.
   */
  public function getGeneratePluginId(): ?string;

  /**
   * Sets deploy plugin.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param array $pluginSettings
   *   Plugin settings.
   *
   * @return $this
   */
  public function setDeployPlugin(string $pluginId, array $pluginSettings): PreviewStrategyInterface;

  /**
   * Sets generate plugin.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param array $pluginSettings
   *   Plugin settings.
   *
   * @return $this
   */
  public function setGeneratePlugin(string $pluginId, array $pluginSettings): PreviewStrategyInterface;

  /**
   * Sets label.
   *
   * @param string $label
   *   New label.
   *
   * @return $this
   */
  public function setLabel(string $label): PreviewStrategyInterface;

}
