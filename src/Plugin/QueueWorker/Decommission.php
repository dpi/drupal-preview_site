<?php

namespace Drupal\preview_site\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a queue worker for decommissioning preview sites.
 *
 * @QueueWorker(
 *   id = "preview_site_decommission",
 *   title = @Translation("Decommission queue worker"),
 *   cron={"time" = 60}
 * )
 */
class Decommission extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Decommission.
   *
   * @param array $configuration
   *   Config.
   * @param mixed $plugin_id
   *   ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($build = $this->entityTypeManager->getStorage('preview_site_build')->load($data)) {
      assert($build instanceof PreviewSiteBuildInterface);
      $build->decomission();
    }
  }

}
