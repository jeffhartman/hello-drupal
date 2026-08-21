<?php

declare(strict_types=1);

namespace Drupal\hello_migrate\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Parses itinerary days from Quark expedition JSON.
 *
 * @DataParser(
 *   id = "json_itinerary_days",
 *   title = @Translation("JSON itinerary days")
 * )
 */
final class JsonItineraryDays extends Json {

  /**
   * {@inheritdoc}
   */
  protected function getSourceData(
    string $url,
    string|int $item_selector = '',
  ): array {
    // We need the complete response because each itinerary contains its own
    // collection of days.
    $data = parent::getSourceData($url);

    if (empty($data['itineraries']) || !is_array($data['itineraries'])) {
      return [];
    }

    $rows = [];

    foreach ($data['itineraries'] as $itinerary) {
      if (
        !is_array($itinerary) ||
        empty($itinerary['id']) ||
        empty($itinerary['days']) ||
        !is_array($itinerary['days'])
      ) {
        continue;
      }

      $package_code = '';
      if (!empty($itinerary['title']) && is_string($itinerary['title'])) {
        $package_code = trim(strtok($itinerary['title'], ':'));
      }

      foreach ($itinerary['days'] as $day) {
        if (!is_array($day)) {
          continue;
        }

        // Add the parent itinerary ID to each flattened day row.
        $day['itinerary_id'] = $itinerary['id'];
        $day['package_code'] = $package_code;

        $rows[] = $day;
      }
    }

    return $rows;
  }

}
