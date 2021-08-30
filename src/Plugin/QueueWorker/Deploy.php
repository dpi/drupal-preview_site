<?php

namespace Drupal\preview_site\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a queue worker plugin for deployments.
 *
 * @QueueWorker(
 *   id = "preview_site_deploy",
 *   title = @Translation("Deploy queue worker"),
 *   deriver = \Drupal\preview_site\Plugin\Derivative\PreviewSiteBuildQueueWorkerDeriver::class,
 * )
 */
class Deploy extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Generate.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build */
    $build = $this->entityTypeManager->getStorage('preview_site_build')->load($this->pluginDefinition['preview_site_build_id']);
    $item = $build->get('artifacts')->get($data);
    if ($item && ($file = $item->entity) && ($strategy = $build->getStrategy())) {
      $strategy->getDeployPlugin()->deployArtifact($build, $file);
    }
  }

}
