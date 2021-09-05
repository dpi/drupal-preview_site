<?php

declare(strict_types=1);

namespace Drupal\preview_site\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;

/**
 * Defines an interface for preview site plugins.
 */
interface PreviewSitePluginInterface extends ConfigurableInterface, PluginInspectionInterface, PluginWithFormsInterface, PluginFormInterface, DependentPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Gets the plugin title.
   *
   * @return string
   *   Plugin title.
   */
  public function getTitle() : string;

  /**
   * Alters the url to a deployed item.
   *
   * @param string $url
   *   URL.
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Build.
   *
   * @return string
   *   Altered URL.
   */
  public function alterUrlToDeployedItem(string $url, PreviewSiteBuildInterface $build): string;

}
