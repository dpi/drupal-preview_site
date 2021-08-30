<?php

namespace Drupal\preview_site\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a deriver for the generate and deploy queue plugins.
 */
class PreviewSiteBuildQueueWorkerDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GenerateQueueWorkerDeriver.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    $results = $this->entityTypeManager->getStorage('preview_site_build')->getAggregateQuery()->groupBy('label')->groupBy('bid')->execute();
    if (!$results) {
      return $this->derivatives;
    }
    foreach ($results as $build_details) {
      $this->derivatives[$build_details['bid']] = [
        'title' => new TranslatableMarkup('@default for @name', [
          '@default' => $base_plugin_definition['title'],
          '@name' => $build_details['label'],
        ]),
        'preview_site_build_id' => $build_details['bid'],
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
