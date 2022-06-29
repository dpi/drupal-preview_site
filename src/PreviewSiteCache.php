<?php

declare(strict_types=1);

namespace Drupal\preview_site;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Database\Connection;
use Drupal\tome_static\StaticCache;
use Drupal\tome_static\StaticCacheInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates the cache.
 */
final class PreviewSiteCache extends StaticCache {

  private StaticCacheInterface $inner;
  private RequestStack $requestStack;

  /**
   * Constructs a new PreviewSiteCache.
   */
  public function __construct(Connection $connection, CacheTagsChecksumInterface $checksum_provider, StaticCacheInterface $inner, RequestStack $requestStack) {
    parent::__construct($connection, $checksum_provider);
    $this->inner = $inner;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public function filterUncachedPaths($base_url, array $original_paths) {
    return $this->inner->filterUncachedPaths($base_url, $original_paths);
  }

  /**
   * {@inheritdoc}
   */
  public function setCache(Request $request, Response $response, $original_path, $destination): void {
    $this->inner->setCache($request, $response, $original_path, $destination);
    if ($response instanceof RedirectResponse) {
      $targetPath = $this->makeExternalUrlLocal($response->getTargetUrl());
      $cidRedirectDestination = sprintf('%s|%s', $request->getSchemeAndHttpHost(), $targetPath);
      $this->inner->set($cidRedirectDestination, $request->getPathInfo());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiredFiles() {
    return $this->inner->getExpiredFiles();
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheEmpty() {
    return $this->inner->isCacheEmpty();
  }

  /**
   * Makes an external URL local.
   *
   * Borrowed from StaticGenerator.
   *
   * @param string $path
   *   A URL.
   *
   * @return string
   *   Transforms an external URL int oa local path.
   *
   * @see \Drupal\tome_static\StaticGenerator::makeExternalUrlLocal
   */
  protected function makeExternalUrlLocal(string $path): string {
    $components = parse_url($path);
    if (UrlHelper::isExternal($path) && isset($components['host']) && UrlHelper::externalIsLocal($path, $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost())) {
      $path = $components['path'];
      if (!empty($components['query'])) {
        $path .= '?' . $components['query'];
      }
    }
    return $path;
  }

  /**
   * Determine if a redirect destination was the target of a redirect.
   *
   * @param string $schemeAndHost
   *   Scheme and host, as provided from Request::getSchemeAndHttpHost.
   * @param string $targetPath
   *   The redirect destination.
   *
   * @return bool
   *   Whether the redirect destination was the target of a redirect.
   *
   * @see \Symfony\Component\HttpFoundation\Request::getSchemeAndHttpHost
   */
  public function isRedirect(string $schemeAndHost, string $targetPath): bool {
    $cidRedirectDestination = sprintf('%s|%s', $schemeAndHost, $targetPath);
    return $this->get($cidRedirectDestination) !== FALSE;
  }

}
