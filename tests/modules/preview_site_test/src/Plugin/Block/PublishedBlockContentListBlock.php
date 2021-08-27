<?php

namespace Drupal\preview_site_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a block that uses an entity query to show a list of blocks.
 *
 * @Block(
 *   id = "preview_site_test_published_blocks",
 *   admin_label = @Translation("Preview site published blocks"),
 *   category = @Translation("Preview site"),
 * )
 *
 * @codeCoverageIgnore
 */
class PublishedBlockContentListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
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
  public function build() {
    $builder = $this->entityTypeManager->getViewBuilder('block_content');
    $storage = $this->entityTypeManager->getStorage('block_content');
    $blocks = $storage->getQuery()
      ->condition('reusable', 1)
      ->condition('status', 1)
      ->execute();
    if (!$blocks) {
      return [];
    }
    return $builder->viewMultiple($storage->loadMultiple($blocks));
  }

}
