<?php

namespace Drupal\Tests\preview_site\Functional;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\preview_site\Entity\PreviewStrategy;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\preview_site\Traits\PreviewSiteTestTrait;

/**
 * Defines a test for building preview sites via the deploy form.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Form\PreviewSiteBuildDeployForm
 */
class PreviewSiteBuildDeployFormTest extends BrowserTestBase {

  use PreviewSiteTestTrait;
  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;
  use ParagraphsTestBaseTrait, ContentModerationTestTrait {
    addParagraphsField as addParagraphsField;
    addParagraphsType as addParagraphsType;
    addFieldtoParagraphType as addFieldToParagraphType;
    ContentModerationTestTrait::createEditorialWorkflow insteadof ParagraphsTestBaseTrait;
  }

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
    'options',
    'tome_static',
    'datetime',
    'field',
    'node',
    'options',
    'text',
    'workflows',
    'content_moderation',
    'paragraphs',
    'path',
    'path_alias',
    'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tome strategy.
   *
   * @var \Drupal\preview_site\Entity\PreviewStrategy
   */
  protected $strategy;

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
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');

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
    $this->strategy->save();

    $this->addParagraphsField('page', 'paragraphs', 'node');
    $this->addParagraphsType('default');
    $this->addFieldtoParagraphType('default', 'content', 'text_long');
  }

  /**
   * Tests deployment.
   */
  public function testDeploymentViaUi() {
    $draft_text = $this->getRandomGenerator()->sentences(10);
    $published_text = $this->getRandomGenerator()->sentences(10);
    // Published version.
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $published_text,
      ],
    ]);
    $node->save();

    // Draft version with a paragraph and new text.
    $node->body->value = $draft_text;
    $paragraph_text = $this->getRandomGenerator()->sentences(10);

    $paragraph = Paragraph::create([
      'type' => 'default',
      'content' => [
        'format' => 'plain_text',
        'value' => $paragraph_text,
      ],
    ]);
    $node->paragraphs = $paragraph;
    $node->moderation_state = 'draft';
    $node->setNewRevision(TRUE);
    $node->save();

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($build->toUrl('deploy-form'));
    $this->submitForm([], 'Confirm');
    $this->checkForMetaRefresh();
    $assert = $this->assertSession();
    $build = PreviewSiteBuild::load($build->id());
    $this->htmlOutput(var_export(array_column($build->get('log')->getValue(), 'value'), TRUE));
    $assert->pageTextContains('The preview site was successfully built');
    $assert->linkExists('View logs');
    $assert->linkExists('Build and Deploy');
    $this->clickLink('View logs');

    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $path = file_create_url($node_static_file);
    $this->drupalGet($path);
    $assert->statusCodeEquals(200);
    $assert->pageTextContains($draft_text);
    $assert->pageTextContains($paragraph_text);
    $assert->pageTextNotContains($published_text);

    $css_file = $assert->elementExists('css', 'link[rel=stylesheet]')->getAttribute('href');
    $artifacts_uris = $this->getArtifactUris($build, \Drupal::state()->get(sprintf('preview_site_build_files:%s', $build->uuid())));
    $this->assertContains(parse_url($css_file, PHP_URL_PATH), $artifacts_uris);

    // Check that the draft doesn't bleed into the live site.
    $this->drupalLogout();
    $this->drupalGet($node->toUrl());
    $assert->pageTextNotContains($draft_text);
    $assert->pageTextContains($published_text);
    $assert->pageTextNotContains($paragraph_text);
  }

}
