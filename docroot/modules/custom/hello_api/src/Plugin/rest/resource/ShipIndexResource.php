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
 * Provides ship index data as a REST resource.
 */
#[RestResource(
  id: 'ship_index',
  label: new TranslatableMarkup('Ship Index'),
  uri_paths: [
    'canonical' => '/api/ships',
  ],
)]
final class ShipIndexResource extends ResourceBase {

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
      ->condition('type', 'ship')
      ->condition('field_operator.target_id', 15)
      ->condition('status', 1)
      ->sort('title')
      ->execute();

    $nodes = $storage->loadMultiple($node_ids);

    $resource = [];
    $resource['result_count'] = count($nodes);
    foreach ($nodes as $node) {
      $operator = $node->get('field_operator')->entity;

      $ship_hero_image = $this->heroBannerData->build($node);
      $ship_card_image = $this->cardImageData->build($node);

      $resource['data'][] = [
        'id' => (int) $node->id(),
        'ship_id' => !$node->get('field_ship_id')->isEmpty()
          ? $node->get('field_ship_id')->value
          : NULL,
        'ship_name' => $node->label(),
        'absolute_url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'relative_url' => $node->toUrl('canonical', ['absolute' => FALSE])->toString(),
        'ship_imo_number' => (int) $node->get('field_imo_number')->value ?: NULL,
        'ship_hero_image' => $ship_hero_image,
        'ship_card_image' => $ship_card_image,
        'ship_description' => $node->get('field_body')->value ?: NULL,
        'operator_id' => $operator ? (int) $operator->id() : NULL,
        'operator_name' => $operator?->label(),
      ];
    }

    $response = new ResourceResponse($resource);
    $response->getCacheableMetadata()->addCacheTags(['node_list:ship']);

    return $response;
  }

}
