<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_site\Traits;

/**
 * Defines a trait for capturing logs.
 *
 * @codeCoverageIgnore
 */
trait LoggingTrait {

  private string $testLoggerServiceName = 'test.logger';

  /**
   * Gets logs from buffer and cleans out buffer.
   *
   * Reconstructs logs into plain strings.
   *
   * @param array|null $logBuffer
   *   A log buffer from getLogBuffer, or provide an existing value fetched from
   *   getLogBuffer. This is a workaround for the logger clearing values on
   *   call.
   *
   * @return array
   *   Logs from buffer, where values are an array with keys: severity, message.
   */
  protected function getLogs(?array $logBuffer = NULL): array {
    $logs = array_map(function (array $log) {
      [$severity, $message, $context] = $log;
      return [
        'severity' => $severity,
        'message' => str_replace(array_keys($context), array_values($context), $message),
      ];
    }, $logBuffer ?? $this->getLogBuffer());
    return array_values($logs);
  }

  /**
   * Gets logs from buffer and cleans out buffer.
   *
   * @array
   *   Logs from buffer, where values are an array with keys: severity, message.
   */
  protected function getLogBuffer(): array {
    return $this->container->get($this->testLoggerServiceName)->cleanLogs();
  }

}
