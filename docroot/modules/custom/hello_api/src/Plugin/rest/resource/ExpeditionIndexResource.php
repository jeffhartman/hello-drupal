<?php

declare(strict_types=1);

namespace Drupal\hello_api\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hello_api\Service\CardImageDataService;
use Drupal\hello_api\Service\HeroBannerDataService;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides expedition index data as a REST resource.
 */
#[RestResource(
  id: 'expedition_index',
  label: new TranslatableMarkup('Expedition Index'),
  uri_paths: [
    'canonical' => '/api/expeditions',
  ],
)]
final class ExpeditionIndexResource extends ResourceBase {

  /**
   * The entity type manager.
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * The hero banner data service.
   */
  private readonly HeroBannerDataService $heroBannerData;

  /**
   * The card image data service.
   */
  private readonly CardImageDataService $cardImageData;

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
    HeroBannerDataService $heroBannerData,
    CardImageDataService $cardImageData,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entityTypeManager;
    $this->heroBannerData = $heroBannerData;
    $this->cardImageData = $cardImageData;

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
      $container->get('entity_type.manager'),
      $container->get('hello_api.hero_banner_data'),
      $container->get('hello_api.card_image_data'),
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get(): ResourceResponse {
    $storage = $this->entityTypeManager->getStorage('node');

    $node_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'expedition')
      ->sort('title')
//      ->range(0, 3)
      ->execute();

    $nodes = $storage->loadMultiple($node_ids);

    $resource = [];
    $resource['result_count'] = count($nodes);
    foreach ($nodes as $node) {
      $operator = $node->get('field_operator')->entity;
      $expedition_hero_image = $this->heroBannerData->build($node);
      $expedition_card_image = $this->cardImageData->build($node);

      $destinations = [];
      foreach ($node->get('field_destinations')->referencedEntities() as $destination) {
        $destinations[] = $destination->label();
      }
      sort($destinations);

      $resource['data'][] = [
        'id' => (int) $node->id(),
        'expedition_name' => $node->label(),
        'expedition_description' => $node->get('field_body')->value ?: NULL,
        'expedition_hero_image' => $expedition_hero_image,
        'expedition_card_image' => $expedition_card_image,
        'absolute_url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'relative_url' => $node->toUrl('canonical', ['absolute' => FALSE])->toString(),
        'operator_id' => $operator ? (int) $operator->id() : NULL,
        'operator_name' => $operator?->label(),
        'region_id' => $node->get('field_primary_destination')->entity->get('field_destination_id')->value,
        'region_name' => $node->get('field_primary_destination')->entity->label(),
        'destinations' => $destinations,
      ];
    }

    $response = new ResourceResponse($resource);
    $response->getCacheableMetadata()->addCacheTags(['node_list:expedition']);

    return $response;
  }

}
