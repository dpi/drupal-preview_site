<?php

namespace Drupal\preview_site\EntityHandlers\ParentNegotiation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a default parent negotiation class.
 */
class DefaultParentNegotiation implements ParentNegotiationInterface {

  /**
   * {@inheritdoc}
   */
  public function getParentEntity(ContentEntityInterface $entity): ?ContentEntityInterface {
    return NULL;
  }

}
