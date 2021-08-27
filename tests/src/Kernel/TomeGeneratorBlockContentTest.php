<?php

namespace Drupal\Tests\preview_site\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;
use Drupal\preview_site\Entity\PreviewSiteBuild;
use Drupal\Tests\preview_site\Traits\BlockContentSetupTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a class for testing tome generator with block content.
 *
 * @group preview_site
 */
class TomeGeneratorBlockContentTest extends TomeGeneratorTestBase {

  use BlockContentSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupBlockContentType();
  }

  /**
   * Tests generation, with block in draft.
   */
  public function testGenerationWithBlockInDraft() {
    $block_body = $this->getRandomGenerator()->sentences(10);
    $block = BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_body,
      ],
      'status' => 0,
      'moderation_state' => 'draft',
      'reusable' => TRUE,
    ]);
    $this->placeBlock('block_content:' . $block->uuid(), [
      'region' => 'content',
    ]);
    $body = $this->getRandomGenerator()->sentences(10);
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $body,
      ],
    ]);
    $node->save();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node, $block],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $body))->count());
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $block_body))->count());
  }

  /**
   * Tests generation, with an entity query that has a status condition.
   */
  public function testGenerationWithEntityQuery() {
    $block_body = $this->getRandomGenerator()->sentences(10);
    $block = BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_body,
      ],
      'status' => 0,
      'moderation_state' => 'draft',
      'reusable' => TRUE,
    ]);
    $this->placeBlock('preview_site_test_published_blocks', [
      'region' => 'content',
    ]);
    $body = $this->getRandomGenerator()->sentences(10);
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $body,
      ],
    ]);
    $node->save();
    $build = $this->createPreviewSiteBuild([
      'strategy' => $this->strategy->id(),
      'contents' => [$node, $block],
      'artifacts' => NULL,
      'processed_paths' => NULL,
      'log' => NULL,
    ]);
    $this->genererateAndDeployBuild($build);
    $build = PreviewSiteBuild::load($build->id());
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $body))->count());
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $block_body))->count());
  }

  /**
   * Tests generation, with an entity query that has a status condition.
   */
  public function testGenerationWithEntityQueryButNoBlockContentInContents() {
    $block_body = $this->getRandomGenerator()->sentences(10);
    BlockContent::create([
      'type' => $this->type->id(),
      'body' => [
        'format' => 'plain_text',
        'value' => $block_body,
      ],
      'status' => 0,
      'moderation_state' => 'draft',
      'reusable' => TRUE,
    ]);
    $this->placeBlock('preview_site_test_published_blocks', [
      'region' => 'content',
    ]);
    $body = $this->getRandomGenerator()->sentences(10);
    $node = Node::create([
      'status' => 1,
      'moderation_state' => 'published',
      'title' => $this->randomMachineName(),
      'type' => 'page',
      'body' => [
        'format' => 'plain_text',
        'value' => $body,
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
    $node_static_file = $this->getGeneratedFileForEntity($node, $build);
    $this->assertTrue(file_exists($node_static_file));

    $crawler = new Crawler(file_get_contents($node_static_file));
    $this->assertGreaterThan(0, $crawler->filter(sprintf('div:contains("%s")', $body))->count());
    // There should be no block, because we didn't add it to the build contents.
    $this->assertEquals(0, $crawler->filter(sprintf('div:contains("%s")', $block_body))->count());
  }

}
