<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\embed\Entity\EmbedButton;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\Tests\preview_site\Traits\BlockContentSetupTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a class for testing integration with entity-embed.
 *
 * @group preview_site
 * @covers \Drupal\preview_site\Plugin\PreviewSite\Generate\TomeGenerator
 * @covers \Drupal\preview_site\Generate\TomeStaticExtension
 */
class TomeGeneratorEntityEmbedTest extends TomeGeneratorParagraphTest {

  use BlockContentSetupTrait;

  /**
   * {@inheritdoc}
   *
   * Entity embed doesn't have a valid schema.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_embed', 'embed', 'block_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupBlockContentType();

    $this->installConfig(['entity_embed', 'embed']);
    $format = FilterFormat::create([
      'format' => 'embed',
      'name' => 'Embed',
      'filters' => [
        'entity_embed' => [
          'id' => 'entity_embed',
          'provider' => 'entity_embed',
          'status' => TRUE,
        ],
      ],
    ]);
    $format->save();
    $this->config('entity_embed.settings')
      ->set('track_enabled_source_entity_types', ['node', 'block_content', 'paragraph'])
      ->set('track_enabled_target_entity_types', ['block_content'])
      ->set('track_enabled_plugins', ['entity_embed', 'entity_reference'])
      ->save();
    $embed = EmbedButton::create([
      'id' => 'default',
      'type_id' => 'entity',
      'type_settings' => [
        'entity_type' => 'block_content',
        'bundles' => [$this->type->id()],
        'display_plugins' => ['view_mode:block_content.full'],
      ],
    ]);
    $embed->save();
  }

  /**
   * Tests generation, with entity-embed.
   */
  public function testGenerationWithEntityEmbed() {
    $block_body = $this->getRandomGenerator()->sentences(10);
    $block = BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_body,
      ],
      'status' => 1,
      'moderation_state' => 'published',
      'reusable' => TRUE,
    ]);
    $block->save();
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'embed',
        'value' => sprintf('<drupal-entity
          data-embed-button="default"
          data-entity-embed-display="view_mode:block_content.full"
          data-entity-type="block_content"
          data-entity-uuid="%s"
          data-langcode="en">', $block->uuid()),
      ],
    ]);
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
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $block_body))->count());
  }

  /**
   * Tests generation, with entity-embed in draft.
   */
  public function testGenerationWithEntityEmbedInDraft() {
    $block_published = $this->getRandomGenerator()->sentences(10);
    $block = BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_published,
      ],
      'status' => 1,
      'moderation_state' => 'published',
      'reusable' => TRUE,
    ]);
    $block->save();
    $block_draft = $this->getRandomGenerator()->sentences(10);
    $block->body->value = $block_draft;
    $block->status = 0;
    $block->moderation_state = 'draft';
    $block->setNewRevision(TRUE);
    $block->save();

    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'embed',
        'value' => sprintf('<drupal-entity
          data-embed-button="default"
          data-entity-embed-display="view_mode:block_content.full"
          data-entity-type="block_content"
          data-entity-uuid="%s"
          data-langcode="en">', $block->uuid()),
      ],
    ]);
    $node->save();

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    // There is a static-cache entry from entity-usage when the block was
    // created.
    \Drupal::entityTypeManager()->getStorage('block_content')->resetCache();
    // Artificially generate a circular entity-usage reference.
    \Drupal::service('entity_usage.usage')->registerUsage($node->id(), 'node', $block->id(), 'block_content', $block->language()->getId(), $block->getRevisionId(), 'entity_reference', '_dummy');
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $block_draft))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $block_published))->count());
  }

  /**
   * Tests generation, with paragraphs.
   */
  public function testWithParagraphs() {
    $block_published = $this->getRandomGenerator()->sentences(10);
    $block = BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_published,
      ],
      'status' => 1,
      'moderation_state' => 'published',
      'reusable' => TRUE,
    ]);
    $block->save();
    $block_draft = $this->getRandomGenerator()->sentences(10);
    $block->body->value = $block_draft;
    $block->status = 0;
    $block->moderation_state = 'draft';
    $block->setNewRevision(TRUE);
    $block->save();

    $paragraph = Paragraph::create([
      'type' => 'default',
      'content' => [
        'format' => 'embed',
        'value' => sprintf('<drupal-entity
          data-embed-button="default"
          data-entity-embed-display="view_mode:block_content.full"
          data-entity-type="block_content"
          data-entity-uuid="%s"
          data-langcode="en">', $block->uuid()),
      ],
    ]);

    $node = Node::create([
      'status' => 0,
      'moderation_state' => 'draft',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'paragraphs' => $paragraph,
    ]);
    $node->save();

    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    \Drupal::entityTypeManager()->getStorage('block_content')->resetCache();

    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $this->assertFalse($build->get('artifacts')->isEmpty());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $block_draft))->count());
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $block_published))->count());
  }

}
