<?php

declare(strict_types=1);

namespace Drupal\hello_migrate\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Parses a single JSON object as one migration source row.
 *
 * @DataParser(
 *   id = "json_single_item",
 *   title = @Translation("JSON single item")
 * )
 */
final class JsonSingleItem extends Json {

  /**
   * {@inheritdoc}
   */
  protected function getSourceData(string $url, string|int $item_selector = ''): array {
    $root_data = parent::getSourceData($url);

    if (
      array_is_list($root_data) &&
      count($root_data) === 1 &&
      is_array($root_data[0])
    ) {
      $root_data = $root_data[0];
    }
    $data = $item_selector === ''
      ? $root_data
      : parent::getSourceData($url, $item_selector);

    // Copy configured values from the root object into selected child rows.
    $parent_fields = $this->configuration['parent_fields'] ?? [];
    if ($parent_fields !== [] && is_array($data)) {
      $is_collection = $data !== []
        && count(array_filter($data, 'is_array')) === count($data);

      if ($is_collection) {
        foreach ($data as &$item) {
          foreach ($parent_fields as $source_key => $destination_key) {
            if (array_key_exists($source_key, $root_data)) {
              $item[$destination_key] = $root_data[$source_key];
            }
          }
        }
        unset($item);
      }
      else {
        foreach ($parent_fields as $source_key => $destination_key) {
          if (array_key_exists($source_key, $root_data)) {
            $data[$destination_key] = $root_data[$source_key];
          }
        }
      }
    }

    if ($data === []) {
      return [];
    }

    // If the selected data is already a collection of row arrays, preserve it.
    // Normalize the keys because API collections may be keyed by IDs.
    if ($data !== [] && count(array_filter($data, 'is_array')) === count($data)) {
      return array_values($data);
    }

    // Otherwise, wrap the selected object as a single source row.
    return [$data];
  }

}
