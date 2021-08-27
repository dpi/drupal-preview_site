<?php

namespace Drupal\preview_site\Deploy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\preview_site\Annotation\PreviewSiteDeploy;

/**
 * Defines a plugin manager for deploy plugins.
 */
class DeployPluginManager extends DefaultPluginManager {

  /**
   * Constructs a DeployPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/PreviewSite/Deploy', $namespaces, $module_handler, DeployPluginInterface::class, PreviewSiteDeploy::class);
    $this->alterInfo('preview_site_deploy');
    $this->setCacheBackend($cache_backend, 'preview_site_deploy');
  }

}
