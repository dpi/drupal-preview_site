<?php

namespace Drupal\preview_site\EntityHandlers;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Defines a route provider for preview_site_build entities.
 */
class PreviewSiteBuildRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();
    $collection->add("entity.{$entity_type_id}.deploy_form",
      (new Route($entity_type->getLinkTemplate('deploy-form')))
        ->addDefaults([
          '_entity_form' => "{$entity_type_id}.deploy",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.update")
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ])
        ->setRequirement($entity_type_id, '\d+'));
    return $collection;
  }

}
