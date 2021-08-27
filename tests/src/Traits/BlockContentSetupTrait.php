<?php

namespace Drupal\Tests\preview_site\Traits;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\workflows\Entity\Workflow;

/**
 * Defines a trait for setting up block-content.
 *
 * @codeCoverageIgnore
 */
trait BlockContentSetupTrait {

  /**
   * Block content type.
   *
   * @var \Drupal\block_content\BlockContentTypeInterface
   */
  protected $type;

  /**
   * Sets up block content-type.
   */
  protected function setupBlockContentType() {
    $this->installConfig(['block_content']);
    $this->installEntitySchema('block_content');
    $this->type = BlockContentType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
    ]);
    $this->type->save();
    $this->addEntityTypeAndBundleToWorkflow(Workflow::load('editorial'), 'block_content', $this->type->id());
    block_content_add_body_field($this->type->id());
  }

}
