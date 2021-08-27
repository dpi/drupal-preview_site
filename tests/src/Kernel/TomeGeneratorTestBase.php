<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Defines a base class for use in testing the tome generator.
 */
abstract class TomeGeneratorTestBase extends PreviewSiteKernelTestBase {

  use NodeCreationTrait;
  use BlockCreationTrait;
  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'workflows',
    'content_moderation',
    'block',
  ];

  /**
   * Tome strategy.
   *
   * @var \Drupal\preview_site\Entity\PreviewStrategy
   */
  protected $strategy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['node', 'filter', 'user', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->createContentType(['type' => 'page']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');
    $this->prefix = $this->randomMachineName();
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->trustData()->save();
    $this->setUpCurrentUser();

    $this->strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'generate' => 'preview_site_tome',
      'deploy' => 'test',
      'generateSettings' => [],
      'deploySettings' => [
        'prefix' => $this->prefix,
      ],
    ]);
    $this->strategy->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem() {
    // Setup the private file scheme.
    parent::setUpFilesystem();
    mkdir($this->siteDirectory . '/files/private', 0775);
    $this->setSetting('file_private_path', $this->siteDirectory . '/files/private');
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Turn path aliases back on.
    parent::register($container);
    $container->getDefinition('path_alias.path_processor')
      ->addTag('path_processor_inbound')
      ->addTag('path_processor_outbound');
  }

}
