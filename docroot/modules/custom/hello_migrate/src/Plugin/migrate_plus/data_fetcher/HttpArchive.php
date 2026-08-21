<?php

declare(strict_types=1);

namespace Drupal\hello_migrate\Plugin\migrate_plus\data_fetcher;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Attribute\DataFetcher;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;

/**
 * Retrieves HTTP migration data and archives changed source payloads.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: dynamic_url
 *   data_fetcher_plugin: http_archive
 *   data_parser_plugin: json_single_item
 *
 *   artifact_directory: 'private://migration-artifacts/qrk/expedition'
 * @endcode
 */
#[DataFetcher(
  id: 'http_archive',
  title: new TranslatableMarkup('HTTP Archive'),
)]
final class HttpArchive extends Http {

  /**
   * {@inheritdoc}
   */
  public function getResponseContent(string $url): string {
    $content = parent::getResponseContent($url);

    $this->archiveContent($url, $content);

    return $content;
  }

  /**
   * Archives the payload if it differs from the latest stored version.
   */
  protected function archiveContent(string $url, string $content): void {
    // Archiving is opt-in. If no artifact directory is configured on the
    // migration source, behave exactly like the standard HTTP data fetcher.
    $artifact_directory = $this->configuration['artifact_directory'] ?? NULL;

    if (empty($artifact_directory)) {
      return;
    }

    // Use the final URL segment as the source-specific directory name. For
    // example, /expedition/125 is archived beneath an artifact directory /125.
    $source_id = $this->getSourceIdFromUrl($url);

    if ($source_id === '') {
      throw new MigrateException(sprintf(
        'Could not determine an artifact source ID from URL "%s".',
        $url,
      ));
    }

    // Build the Drupal stream-wrapper URI for this individual source record.
    $directory = sprintf(
      '%s/%s',
      rtrim($artifact_directory, '/'),
      $source_id,
    );

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    // Native PHP filesystem functions cannot reliably operate on Drupal stream
    // wrapper URIs, so resolve the configured scheme root to a physical path
    // and build the artifact path beneath it. This allows the full artifact
    // directory hierarchy to be created dynamically when it does not exist.
    $scheme = parse_url($artifact_directory, PHP_URL_SCHEME);

    if (!is_string($scheme) || $scheme === '') {
      throw new MigrateException(sprintf(
        'Artifact directory "%s" must use a Drupal stream-wrapper URI.',
        $artifact_directory,
      ));
    }

    $scheme_root_uri = $scheme . '://';

    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
    $stream_wrapper = $stream_wrapper_manager->getViaScheme($scheme);

    if ($stream_wrapper === FALSE || !method_exists($stream_wrapper, 'getDirectoryPath')) {
      throw new MigrateException(sprintf(
        'Could not resolve a local stream-wrapper directory for "%s".',
        $scheme_root_uri,
      ));
    }

    $scheme_root_path = $stream_wrapper->getDirectoryPath();

    if (!is_string($scheme_root_path) || $scheme_root_path === '') {
      throw new MigrateException(sprintf(
        'Stream wrapper "%s" did not provide a valid directory path.',
        $scheme_root_uri,
      ));
    }

    $artifact_relative_path = ltrim(
      substr($artifact_directory, strlen($scheme_root_uri)),
      '/',
    );

    $directory_path = sprintf(
      '%s/%s/%s',
      rtrim($scheme_root_path, '/'),
      trim($artifact_relative_path, '/'),
      $source_id,
    );

    // Create the source-specific artifact directory when necessary and ensure
    // that Drupal has appropriate permissions to write to it.
    if (!$file_system->prepareDirectory(
      $directory_path,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      throw new MigrateException(sprintf(
        'Could not create migration artifact directory "%s".',
        $directory,
      ));
    }

    // latest.json always represents the most recently archived payload and is
    // used to determine whether the newly fetched source data has changed.
    $latest_uri = $directory . '/latest.json';
    $latest_path = $directory_path . '/latest.json';
    $current_hash = hash('sha256', $content);

    // If the payload has not changed, do not create another artifact.
    if (file_exists($latest_path)) {
      $latest_content = file_get_contents($latest_path);

      if (
        $latest_content !== FALSE &&
        hash('sha256', $latest_content) === $current_hash
      ) {
        return;
      }
    }

    // The payload changed (or this is the first fetch), so preserve a
    // timestamped, hash-qualified snapshot before updating latest.json.
    $timestamp = gmdate('Y-m-d_H-i-s');
    $archive_uri = sprintf(
      '%s/%s-%s.json',
      $directory,
      $timestamp,
      substr($current_hash, 0, 12),
    );

    // Save the immutable historical snapshot.
    $file_system->saveData(
      $content,
      $archive_uri,
      FileExists::Replace,
    );

    // Replace latest.json with the newly fetched payload for the next
    // comparison.
    $file_system->saveData(
      $content,
      $latest_uri,
      FileExists::Replace,
    );
  }

  /**
   * Gets the source identifier from the final URL path segment.
   */
  protected function getSourceIdFromUrl(string $url): string {
    $path = parse_url($url, PHP_URL_PATH);

    if (!is_string($path)) {
      return '';
    }

    return basename(rtrim($path, '/'));
  }

}
