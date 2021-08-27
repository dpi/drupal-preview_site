<?php

namespace Drupal\preview_site\EntityHandlers\ParentNegotiation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines an interface for negotiating the parent of an entity.
 */
interface ParentNegotiationInterface {

  /**
   * Gets the parent entity for a given entity if applicable.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to get the parent for.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Parent entity or NULL if one does not exist.
   */
  public function getParentEntity(ContentEntityInterface $entity) : ?ContentEntityInterface;

}
