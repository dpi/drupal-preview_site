<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a class for testing nodes with paragraphs.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Plugin\PreviewSite\Generate\TomeGenerator
 * @covers \Drupal\preview_site\Generate\TomeStaticExtension
 */
class TomeGeneratorParagraphTest extends TomeGeneratorTestBase {

  use ParagraphsTestBaseTrait, ContentModerationTestTrait {
    addParagraphsField as addParagraphsField;
    addParagraphsType as addParagraphsType;
    addFieldtoParagraphType as addFieldToParagraphType;
    ContentModerationTestTrait::createEditorialWorkflow insteadof ParagraphsTestBaseTrait;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['paragraphs', 'entity_reference_revisions'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('paragraph');
    $this->addParagraphsField('page', 'paragraphs', 'node');
    $this->addParagraphsType('default');
    $this->addFieldtoParagraphType('default', 'content', 'text_long');
  }

  /**
   * Tests a node with a draft paragraph.
   */
  public function testWithParagraphs() {
    $published_text = $this->getRandomGenerator()->sentences(10);
    $paragraph = Paragraph::create([
      'type' => 'default',
      'content' => [
        'format' => 'plain_text',
        'value' => $published_text,
      ],
    ]);
    $node = Node::create([
      'status' => 1,
      'type' => 'page',
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'paragraphs' => $paragraph,
    ]);
    $node->save();
    $draft_text = $this->getRandomGenerator()->sentences(10);
    $paragraph->content->value = $draft_text;
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
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
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $draft_text))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $published_text))->count());
  }

}
