<?php

namespace Drupal\preview_site\Generate;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Site\Settings;
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
   * {@inheritdoc}
   */
  public function __construct(HttpKernelInterface $http_kernel, RequestStack $request_stack, EventDispatcherInterface $event_dispatcher, StaticCacheInterface $cache, AccountSwitcherInterface $account_switcher, FileSystemInterface $file_system) {
    parent::__construct($http_kernel, $request_stack, $event_dispatcher, $cache, $account_switcher, $file_system);
    $this->staticDirectory = Settings::get('tome_static_directory', '../html');
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

  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  protected function copyPath($path, $destination) {
    $result = parent::copyPath($path, $destination);
    if ($result) {
      $this->copiedPaths[] = $destination;
    }
    return $result;
  }

  /**
   * Gets value of CopiedPaths.
   *
   * @return string[]
   *   Value of CopiedPaths.
   */
  public function getCopiedPaths(): array {
    return $this->copiedPaths;
  }

  /**
   * Resets copied paths.
   */
  public function resetCopiedPaths() : void {
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

}
