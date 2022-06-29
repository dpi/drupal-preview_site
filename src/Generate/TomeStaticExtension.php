<?php

namespace Drupal\preview_site\Generate;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Site\Settings;
use Drupal\preview_site\Entity\PreviewSiteBuildInterface;
use Drupal\preview_site\Plugin\QueueWorker\ProcessCopiedFiles;
use Drupal\tome_static\StaticCacheInterface;
use Drupal\tome_static\StaticGenerator;
use Drupal\tome_static\StaticGeneratorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Defines an extension to tome's static generator.
 */
class TomeStaticExtension extends StaticGenerator {

  /**
   * Static directory.
   *
   * @var string
   */
  protected $staticDirectory;

  /**
   * Copied paths.
   *
   * @var string[]
   *
   * @deprecated is deprecated in preview_site:1.1.3 and is removed from
   *   preview_site:2.0.0. There is no replacement.
   */
  protected $copiedPaths = [];

  /**
   * Tracks if generation is in progress.
   *
   * Whilst Tome sets an attribute on the Request, because the entity we're
   * dealing with is loaded before the request stack is updated, we don't have
   * access to that when deciding which entity to load. So we keep track of
   * state internally.
   *
   * @var bool
   *
   * @see \Drupal\preview_site\Generate\GeneratePluginInterface::entityPreload
   * @see \Drupal\preview_site\Generate\GeneratePluginInterface::generateBuildForAssetPath
   */
  protected $isGenerating = FALSE;

  /**
   * Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Preview site being built.
   *
   * @var \Drupal\preview_site\Entity\PreviewSiteBuildInterface
   */
  protected $previewSiteBuild;

  /**
   * {@inheritdoc}
   */
  public function __construct(HttpKernelInterface $http_kernel, RequestStack $request_stack, EventDispatcherInterface $event_dispatcher, StaticCacheInterface $cache, AccountSwitcherInterface $account_switcher, FileSystemInterface $file_system, QueueFactory $queueFactory) {
    parent::__construct($http_kernel, $request_stack, $event_dispatcher, $cache, $account_switcher, $file_system);
    $this->staticDirectory = Settings::get('tome_static_directory', '../html');
    $this->queueFactory = $queueFactory;
  }

  /**
   * Sets static directory.
   *
   * @param string $staticDirectory
   *   Static directory.
   *
   * @return $this
   */
  public function setStaticDirectory(string $staticDirectory): StaticGeneratorInterface {
    $this->staticDirectory = $staticDirectory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStaticDirectory() {
    return $this->staticDirectory;
  }

  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

  /**
   * {@inheritdoc}
   */
  public function getDestination($path) {
    // We are overriding here to change the visibility. The parent method is
    // protected, but we need this to get the destination of asset paths.
    return parent::getDestination($path);
  }

  /**
   * Sets the preview site build.
   *
   * @param \Drupal\preview_site\Entity\PreviewSiteBuildInterface $build
   *   The preview site build.
   *
   * @return $this
   */
  public function setPreviewSiteBuild(PreviewSiteBuildInterface $build) {
    $this->previewSiteBuild = $build;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exportPaths(array $paths) {
    $paths = array_diff($paths, $this->getExcludedPaths());
    $paths = array_values(array_unique($paths));

    $invoke_paths = [];

    foreach ($paths as $path) {
      $path = $this->makeExternalUrlLocal($path);
      if (UrlHelper::isExternal($path)) {
        continue;
      }
      $destination = $this->getDestination($path);

      $sanitized_path = $this->sanitizePath($path);
      if (!$this->copyPath($sanitized_path, $destination)) {
        $invoke_paths[] = $path;
      }
    }

    return $this->filterInvokePaths($invoke_paths, $this->currentRequest);
  }

  /**
   * {@inheritdoc}
   */
  protected function copyPath($path, $destination) {
    if (!$this->previewSiteBuild instanceof PreviewSiteBuildInterface) {
      throw new \LogicException('You cannot call copyPath without setting the preview site build.');
    }

    $path = TomeStaticExtension::basePath(urldecode($path));
    if (file_exists($path)) {
      $queue = $this->queueFactory->get(ProcessCopiedFiles::PLUGIN_ID . ':' . $this->previewSiteBuild->id());
      $queue->createItem(ProcessCopiedFiles::createItem($path, $destination));
      $this->copiedPaths[] = $destination;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets value of CopiedPaths.
   *
   * @return string[]
   *   Value of CopiedPaths.
   *
   * @deprecated in preview_site:1.1.3 and is removed from preview_site:2.0.0.
   *   There is no replacement.
   */
  public function getCopiedPaths(): array {
    @trigger_error(__METHOD__ . ' is deprecated in preview_site:1.1.3 and is removed from preview_site:2.0.0. There is no replacement.', \E_USER_DEPRECATED);
    return $this->copiedPaths;
  }

  /**
   * Resets copied paths.
   *
   * @deprecated in preview_site:1.1.3 and is removed from preview_site:2.0.0.
   *   There is no replacement.
   */
  public function resetCopiedPaths(): void {
    @trigger_error(__METHOD__ . ' is deprecated in preview_site:1.1.3 and is removed from preview_site:2.0.0. There is no replacement.', \E_USER_DEPRECATED);

    $this->copiedPaths = [];
  }

  /**
   * Sets value of IsGenerating.
   *
   * @param bool $isGenerating
   *   Value for IsGenerating.
   */
  public function setIsGenerating(bool $isGenerating): TomeStaticExtension {
    $this->isGenerating = $isGenerating;
    return $this;
  }

  /**
   * Gets value of isGenerating.
   *
   * @return bool
   *   Value of isGenerating.
   */
  public function isGenerating(): bool {
    return $this->isGenerating;
  }

  /**
   * Base path thing.
   *
   * @param string $path
   *   A path.
   *
   * @return string
   *   A path.
   */
  public static function basePath(string $path): string {
    $base_path = base_path();
    if ($base_path !== '/') {
      $base_path = ltrim($base_path, '/');
      $pattern = '|^' . preg_quote($base_path, '|') . '|';
      $path = preg_replace($pattern, '', $path);
    }
    return $path;
  }

  /**
   * @param $path
   *   The sanitized path.
   * @param $destination
   *   The path.
   *
   * @return \Generator<string>
   *   Generates paths. Keys have no meaning.
   */
  public function getAssets($path, $destination) {
    if (pathinfo($destination, PATHINFO_EXTENSION) === 'css') {
      yield from $this->getCssAssets(file_get_contents($destination), $path);
    }
    if (pathinfo($destination, PATHINFO_EXTENSION) === 'js') {
      yield from $this->getJavascriptModules(file_get_contents($destination), $path);
    }
  }

}
