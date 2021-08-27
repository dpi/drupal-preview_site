<?php

namespace Drupal\Tests\preview_site\Functional;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Tests\preview_site\Traits\PreviewSiteTestTrait;
use Drupal\Core\Url;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\preview_site\Entity\PreviewStrategyInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Defines a class for testing preview strategy administration.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Form\PreviewStrategyForm
 * @covers \Drupal\preview_site\EntityHandlers\PreviewStrategyListBuilder
 */
class PreviewStrategyAdministrationTest extends BrowserTestBase {

  use PreviewSiteTestTrait;

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
    'datetime',
    'options',
    'tome_static',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
  }

  /**
   * Tests preview-site strategy administration.
   */
  public function testPreviewStrategyAdministration() {
    $this->assertThatAnonymousUserCannotAdministerPreviewStrategies();
    $strategy = $this->assertThatAdminCanAddPreviewStrategies();
    $strategy = $this->assertThatAdminCanEditPreviewStrategies($strategy);
    $this->assertThatAdminCanDeletePreviewStrategies($strategy);
  }

  /**
   * Tests anonymous users can't access strategy admin routes.
   */
  private function assertThatAnonymousUserCannotAdministerPreviewStrategies() : void {
    $strategy = $this->createStrategy();
    $urls = [
      Url::fromRoute('entity.preview_site_strategy.collection'),
      $strategy->toUrl('edit-form'),
      $strategy->toUrl('delete-form'),
      Url::fromRoute('entity.preview_site_build.collection'),
    ];
    foreach ($urls as $url) {
      $this->drupalGet($url);
      try {
        $this->assertSession()->statusCodeEquals(403);
      }
      catch (ExpectationException $e) {
        $this->fail(sprintf('%s : %s', $url->toString(), $e->getMessage()));
      }
    }
  }

  /**
   * Assert that admin can add a preview strategy.
   *
   * @return \Drupal\preview_site\Entity\PreviewStrategyInterface
   *   The added strategy.
   */
  private function assertThatAdminCanAddPreviewStrategies() : PreviewStrategyInterface {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('system.admin_structure'));
    $assert = $this->assertSession();
    $assert->linkExists('Preview sites');
    $this->clickLink('Preview sites');
    $assert->linkExists('Preview site strategies');
    $this->drupalGet(Url::fromRoute('entity.preview_site_strategy.collection'));
    $assert->statusCodeEquals(200);
    $assert->linkExists('Add preview strategy');
    $this->clickLink('Add preview strategy');
    $this->assertStringContainsString(Url::fromRoute('entity.preview_site_strategy.add_form')->toString(), $this->getSession()->getCurrentUrl());
    $strategy_name = $this->randomMachineName();
    $id = mb_strtolower($this->randomMachineName());
    $assert->fieldExists('deploy')->setValue('test');
    $assert->buttonExists('Update deploy plugin')->click();
    $this->submitForm([
      'id' => $id,
      'label' => $strategy_name,
      'deploy' => 'test',
      'generate' => 'test',
      'deploySettings[prefix]' => $this->randomString(),
    ], 'Save');
    $assert->pageTextContains(sprintf('Created the %s Preview Strategy.', $strategy_name));
    $assert->linkExists($strategy_name);
    return PreviewStrategy::load($id);
  }

  /**
   * Assert that admin can edit strategies.
   *
   * @param \Drupal\preview_site\Entity\PreviewStrategyInterface $strategy
   *   Strategy to edit.
   *
   * @return \Drupal\preview_site\Entity\PreviewStrategyInterface
   *   The edited strategy.
   */
  private function assertThatAdminCanEditPreviewStrategies(PreviewStrategyInterface $strategy) : PreviewStrategyInterface {
    $this->drupalGet(Url::fromRoute('entity.preview_site_strategy.collection'));
    $assert = $this->assertSession();
    $edit = $strategy->toUrl('edit-form');
    $assert->linkByHrefExists($edit->toString());
    $this->drupalGet($edit);
    $assert->fieldValueEquals('label', $strategy->label());
    $assert->fieldValueEquals('deploy', $strategy->getDeployPlugin()->getPluginId());
    $assert->fieldValueEquals('generate', $strategy->getGeneratePlugin()->getPluginId());
    $assert->fieldValueEquals('deploySettings[prefix]', $strategy->getDeployPlugin()->getConfiguration()['prefix']);
    $new_name = $this->randomMachineName();
    $this->submitForm([
      'label' => $new_name,
    ], 'Save');
    $assert->pageTextContains(sprintf('Saved the %s Preview Strategy.', $new_name));
    return \Drupal::entityTypeManager()->getStorage('preview_site_strategy')->loadUnchanged($strategy->id());
  }

  /**
   * Assert that admin can delete preview site strategies.
   *
   * @param \Drupal\preview_site\Entity\PreviewStrategyInterface $type
   *   The strategy to delete.
   */
  private function assertThatAdminCanDeletePreviewStrategies(PreviewStrategyInterface $type) : void {
    $this->drupalGet(Url::fromRoute('entity.preview_site_strategy.collection'));
    $assert = $this->assertSession();
    $delete = $type->toUrl('delete-form');
    $assert->linkByHrefExists($delete->toString());
    $this->drupalGet($delete);
    $this->submitForm([], 'Delete');
    $assert->pageTextContains(sprintf('The strategy %s has been deleted.', $type->label()));
  }

}
