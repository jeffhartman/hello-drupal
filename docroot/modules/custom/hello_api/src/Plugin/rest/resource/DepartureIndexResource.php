<?php

declare(strict_types=1);

namespace Drupal\hello_api\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides departure index data as a REST resource.
 */
#[RestResource(
  id: 'departure_index',
  label: new TranslatableMarkup('Departure Index'),
  uri_paths: [
    'canonical' => '/api/departures',
  ],
)]
final class DepartureIndexResource extends ResourceBase {

  /**
   * The entity type manager.
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponse {
    $storage = $this->entityTypeManager->getStorage('node');

    $node_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'departure')
      ->condition('status', 1)
      ->sort('field_departure_date', 'ASC')
      ->range(0, 10)
      ->execute();

    $nodes = $storage->loadMultiple($node_ids);

    $resource = [];
    $resource['result_count'] = count($nodes);
    foreach ($nodes as $node) {
      $expedition = $node->get('field_parent_expedition')->entity;
      $itinerary = $node->get('field_itinerary')->entity;
      $operator = $node->get('field_operator')->entity;
      $ship = $node->get('field_ship')->entity;
      $languages = [];
      foreach ($node->get('field_languages')->referencedEntities() as $language) {
        $language_code = $language->get('field_language_code')->value;
        if ($language_code !== NULL && $language_code !== '') {
          $languages[] = $language_code;
        }
      }
      $embarkation_port = $itinerary?->get('field_embarkation_port')->entity;
      $disembarkation_port = $itinerary?->get('field_disembarkation_port')->entity;

      $resource['data'][] = [
        'id' => (int) $node->id(),
        'title' => $node->label(),
        'season' => $itinerary && !$itinerary->get('field_season')->isEmpty()
          ? (int) $itinerary->get('field_season')->value
          : NULL,
        'operator_id' => $operator ? (int) $operator->id() : NULL,
        'operator_name' => $operator?->label(),
        'expedition_id' => $expedition ? (int) $expedition->id() : NULL,
        'expedition_name' => $expedition?->label(),
        'expedition_url' => $expedition->toUrl('canonical', ['absolute' => FALSE])->toString(),
        'ship_id' => $ship ? (int) $ship->id() : NULL,
        'ship_name' => $ship?->label(),
        'ship_url' => $ship->toUrl('canonical', ['absolute' => FALSE])->toString(),
        'departure_id' => $node->get('field_departure_id')->value ?: NULL,
        'departure_start_date' => $node->get('field_departure_date')->value ?: NULL,
        'departure_end_date' => $node->get('field_departure_date')->end_value ?: NULL,

        // Itinerary.
        'itinerary_id' => $itinerary ? (int) $itinerary->id() : NULL,
        'duration_days' => !$node->get('field_duration')->isEmpty()
          ? (int) $node->get('field_duration')->value
          : NULL,
        'embarkation_port' => $embarkation_port?->label(),
        'disembarkation_port' => $disembarkation_port?->label(),
        'embarkation_port_code' => $embarkation_port?->get('field_port_code')->value,
        'disembarkation_port_code' => $disembarkation_port?->get('field_port_code')->value,
        'languages' => $languages,
      ];
    }

    $response = new ResourceResponse($resource);
    $response->getCacheableMetadata()->addCacheTags(['node_list:departure']);

    return $response;
  }

}
