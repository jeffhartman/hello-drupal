<?php

declare(strict_types=1);

namespace Drupal\hello_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate_plus\DataParserPluginManager;

/**
 * Builds detail URLs dynamically from an index endpoint.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: dynamic_url
 *   data_fetcher_plugin: http
 *   data_parser_plugin: json_single_item
 *   index_url: https://example.com/api/items
 *   detail_url: https://example.com/api/items/{id}
 *   exclude_ids:
 *     - 123
 *     - 456
 * @endcode
 *
 * @MigrateSource(
 *   id = "dynamic_url"
 * )
 */
final class DynamicUrl extends Url {

  /**
   * Constructs a DynamicUrl source plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    DataParserPluginManager $parserPluginManager,
  ) {
    if (empty($configuration['index_url'])) {
      throw new \InvalidArgumentException('The "index_url" source configuration is required.');
    }

    if (empty($configuration['detail_url'])) {
      throw new \InvalidArgumentException('The "detail_url" source configuration is required.');
    }

    $configuration['urls'] = static::buildUrls(
      $configuration['index_url'],
      $configuration['detail_url'],
      $configuration['exclude_ids'] ?? [],
    );

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $parserPluginManager,
    );
  }

  /**
   * Builds detail URLs from the keys returned by an index endpoint.
   *
   * @return string[]
   *   The generated detail URLs.
   */
  protected static function buildUrls(
    string $index_url,
    string $detail_url,
    array $exclude_ids = [],
  ): array {
    $response = \Drupal::httpClient()->request('GET', $index_url);

    $data = json_decode(
      (string) $response->getBody(),
      TRUE,
      512,
      JSON_THROW_ON_ERROR,
    );

    if (!is_array($data)) {
      throw new \UnexpectedValueException(sprintf(
        'The index endpoint "%s" did not return a JSON object or array.',
        $index_url,
      ));
    }

    $urls = [];
    $exclude_ids = array_map('strval', $exclude_ids);

    foreach (array_keys($data) as $id) {
      if (in_array((string) $id, $exclude_ids, TRUE)) {
        continue;
      }

      $urls[] = str_replace('{id}', (string) $id, $detail_url);
    }

    return $urls;
  }

}
