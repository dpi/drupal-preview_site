<?php

namespace Drupal\Tests\preview_site\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\preview_site\Traits\PreviewSiteTestTrait;

/**
 * Defines a test for administering preview site builds.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Form\PreviewSiteBuildForm
 * @covers \Drupal\preview_site\EntityHandlers\PreviewSiteBuildListBuilder
 */
class PreviewSiteBuildAdministrationTest extends BrowserTestBase {

  use PreviewSiteTestTrait;
  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;

  /**
   * User interface.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'preview_site',
    'preview_site_test',
    'dynamic_entity_reference',
    'block',
    'field_ui',
    'options',
    'datetime',
    'tome_static',
    'field',
    'node',
    'options',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Expiry date.
   *
   * @var \DateTime
   */
  protected $expire;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->adminUser = $this->createUser([
      'administer preview_site strategies',
      'administer preview_site builds',
      'access administration pages',
    ]);
    $this->drupalPlaceBlock('local_actions_block');
    $this->createContentType(['type' => 'page']);

    $this->prefix = $this->randomMachineName();
    $this->strategy = PreviewStrategy::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'generate' => 'preview_site_tome',
      'deploy' => 'test',
      'generateSettings' => [],
      'deploySettings' => [
        'prefix' => $this->prefix,
      ],
    ]);
    $this->expire = new \DateTime('now');
    $this->strategy->save();
  }

  /**
   * Tests admin UI.
   */
  public function testPreviewSiteBuildUi() {
    $this->assertThatAnonymousCantViewList();
    $this->drupalLogin($this->adminUser);
    $this->assertThatAdminCanViewPreviewSiteBuildList();
    $build = $this->assertAdminCanAddPreviewSites();
    $this->assertAdminCanEditPreviewSites($build);
    $this->drupalLogout();
    $this->assertAnonymousCannotEditPreviewSites($build);
  }

  /**
   * Tests that admin can view preview site build list.
   */
  private function assertThatAdminCanViewPreviewSiteBuildList() : void {
    $this->drupalGet(Url::fromRoute('entity.preview_site_build.collection'));
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->linkExists('Add preview site build');
  }

  /**
   * Tests anonymous can't view list.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function assertThatAnonymousCantViewList() {
    $this->drupalGet(Url::fromRoute('entity.preview_site_build.collection'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests admin can add preview sites.
   *
   * @return \Drupal\preview_site\Entity\PreviewSiteBuildInterface
   *   Created item
   */
  private function assertAdminCanAddPreviewSites() : PreviewSiteBuildInterface {
    $this->clickLink('Add preview site build');
    $name = $this->randomMachineName();
    $node = Node::create([
      'status' => 1,
      'title' => $this->randomMachineName(),
      'type' => 'page',
    ]);
    $node->save();

    $this->submitForm([
      'label[0][value]' => $name,
      'contents[0][target_id]' => sprintf('%s (%s)', $node->label(), $node->id()),
      'expiry_date[0][value][date]' => $this->expire->format('Y-m-d'),
      'expiry_date[0][value][time]' => $this->expire->format('h:i:s'),
    ], 'Save');
    $this->assertSession()->pageTextContains('Created new preview site build');
    $items = \Drupal::entityTypeManager()->getStorage('preview_site_build')->loadByProperties(['label' => $name]);
    return reset($items);
  }

  /**
   * Assert admins can edit preview site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Preview site build.
   */
  private function assertAdminCanEditPreviewSites(PreviewSiteBuildInterface $build) {
    $this->drupalGet($build->toUrl('edit-form'));
    $assert = $this->assertSession();
    $assert->fieldValueEquals('label[0][value]', $build->label());
    $node = $build->contents->entity;
    $assert->fieldValueEquals('contents[0][target_id]', sprintf('%s (%s)', $node->label(), $node->id()));
    $assert->fieldValueEquals('expiry_date[0][value][date]', $this->expire->format('Y-m-d'));
    $new_name = $this->randomMachineName();

    $this->submitForm([
      'label[0][value]' => $new_name,
      'contents[0][target_id]' => sprintf('%s (%s)', $node->label(), $node->id()),
    ], 'Save');
    $assert->pageTextContains('Updated preview site build');
  }

  /**
   * Assert admins can't edit preview site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   Preview site build.
   */
  private function assertAnonymousCannotEditPreviewSites(PreviewSiteBuildInterface $build) {
    $this->drupalGet($build->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(403);
  }

}
