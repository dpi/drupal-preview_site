<?php

namespace Drupal\preview_site\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an annotation for preview site generate strategies.
 *
 * Plugin Namespace: Plugin\PreviewSite\Generate.
 *
 * @see \Drupal\preview_site\Generate\GeneratePluginManager
 * @see \Drupal\preview_site\Generate\GeneratePluginInterface
 * @see \Drupal\preview_site\Generate\GeneratePluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class PreviewSiteGenerate extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the provider that owns the plugin.
   *
   * @var string
   */
  public $provider;

  /**
   * The human-readable name of the plugin.
   *
   * This is used as an administrative summary of what the plugin does.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * Additional administrative information about the plugin's behavior.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

}
