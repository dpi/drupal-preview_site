<?php

namespace Drupal\preview_site\Entity;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\preview_site\Deploy\DeployPluginInterface;
use Drupal\preview_site\Generate\GeneratePluginInterface;

/**
 * Defines a config entity to represent a preview site strategy.
 *
 * A preview site strategy comprises a configured deployment plugin and a
 * configured generation plugin.
 *
 * @ConfigEntityType(
 *   id = "preview_site_strategy",
 *   label = @Translation("Preview Strategy"),
 *   label_collection = @Translation("Preview strategies"),
 *   label_singular = @Translation("strategy"),
 *   label_plural = @Translation("strategies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count strategy",
 *     plural = "@count strategies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\preview_site\EntityHandlers\PreviewStrategyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\preview_site\Form\PreviewStrategyForm",
 *       "edit" = "Drupal\preview_site\Form\PreviewStrategyForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "strategy",
 *   bundle_of = "preview_site_build",
 *   admin_permission = "administer preview_site strategies",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/preview-site/strategies/add",
 *     "edit-form" = "/admin/structure/preview-site/strategies/{preview_site_strategy}/edit",
 *     "delete-form" = "/admin/structure/preview-site/strategies/{preview_site_strategy}/delete",
 *     "collection" = "/admin/structure/preview-site/strategies"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "generate",
 *     "deploy",
 *     "generateSettings",
 *     "deploySettings",
 *   }
 * )
 */
class PreviewStrategy extends ConfigEntityBundleBase implements PreviewStrategyInterface {

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $id = NULL;

  /**
   * The entity label.
   *
   * @var string
   */
  protected $label = NULL;

  /**
   * Deploy plugin ID.
   *
   * Use PreviewStrategy::getDeployPlugin() to access the actual plugin.
   *
   * @var string
   */
  protected $deploy = NULL;

  /**
   * Generate plugin ID.
   *
   * Use PreviewStrategy::getGeneratePlugin() to access the actual plugin.
   *
   * @var string
   */
  protected $generate = NULL;

  /**
   * Holds the plugin collection for the deploy plugin.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   */
  protected $deployCollection = NULL;

  /**
   * Holds the plugin collection for the generate plugin.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   */
  protected $generateCollection = NULL;

  /**
   * Deploy settings.
   *
   * @var array
   */
  protected $deploySettings = [];

  /**
   * Generate settings.
   *
   * @var array
   */
  protected $generateSettings = [];

  /**
   * {@inheritdoc}
   */
  public function getDeployPlugin() : ?DeployPluginInterface {
    $this->preparePluginCollections();
    if (is_null($this->deployCollection)) {
      return NULL;
    }
    return $this->deployCollection->get($this->deploy);
  }

  /**
   * {@inheritdoc}
   */
  public function getGeneratePlugin() : ?GeneratePluginInterface {
    $this->preparePluginCollections();
    if (is_null($this->generateCollection)) {
      return NULL;
    }
    return $this->generateCollection->get($this->generate);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    $this->preparePluginCollections();
    return array_filter([
      'deploySettings' => $this->deployCollection,
      'generateSettings' => $this->generateCollection,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // Give the parent method and each deploy/generate  plugin a chance to react
    // to removed dependencies and report if any of them made a change.
    return array_reduce([$this->getDeployPlugin(), $this->getGeneratePlugin()], function ($carry, DependentPluginInterface $plugin) use ($dependencies) {
      return $plugin->onDependencyRemoval($dependencies) || $carry;
    }, parent::onDependencyRemoval($dependencies));
  }

  /**
   * Prepares plugin collections.
   */
  protected function preparePluginCollections(): void {
    if (is_null($this->generateCollection) && !is_null($this->generate) && $this->generate !== '') {
      $this->generateCollection = new DefaultSingleLazyPluginCollection(\Drupal::service('plugin.manager.preview_site_generate'), $this->generate, $this->generateSettings);
    }
    if (is_null($this->deployCollection) && !is_null($this->deploy) && $this->deploy !== '') {
      $this->deployCollection = new DefaultSingleLazyPluginCollection(\Drupal::service('plugin.manager.preview_site_deploy'), $this->deploy, $this->deploySettings);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDeployPlugin(string $pluginId, array $pluginSettings) : PreviewStrategyInterface {
    $this->deploy = $pluginId;
    $this->deploySettings = $pluginSettings;
    $this->deployCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setGeneratePlugin(string $pluginId, array $pluginSettings) : PreviewStrategyInterface {
    $this->generate = $pluginId;
    $this->generateSettings = $pluginSettings;
    $this->generateCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label) : PreviewStrategyInterface {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeployPluginId(): ?string {
    return $this->deploy;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeneratePluginId(): ?string {
    return $this->generate;
  }

}
