<?php

namespace Drupal\preview_site\EntityHandlers\ParentNegotiation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Defines a class for negotiating parents of paragraphs.
 */
class ParagraphParentNegotiation extends DefaultParentNegotiation {

  /**
   * {@inheritdoc}
   */
  public function getParentEntity(ContentEntityInterface $entity): ?ContentEntityInterface {
    assert($entity instanceof ParagraphInterface);
    return $entity->getParentEntity();
  }

}
