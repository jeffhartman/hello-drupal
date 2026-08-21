<?php

namespace Drupal\hello_api\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * Builds normalized hero banner data for API resources.
 */
class HeroBannerDataService {

  /**
   * Constructs a HeroBannerDataService object.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Builds hero banner data for an entity.
   */
  public function build(EntityInterface $entity): ?array {
    $hero_banner = $entity->hasField('field_hero_banner')
      ? $entity->get('field_hero_banner')->entity
      : NULL;

    if (!$hero_banner) {
      return NULL;
    }

    $image_url = NULL;
    $image_title = NULL;
    $image_alt = NULL;
    $responsive_images = [];
    $fallback_image = NULL;
    $hero_title = $hero_banner->hasField('field_hero_banner_title')
      ? ($hero_banner->get('field_hero_banner_title')->value ?: NULL)
      : NULL;
    $hero_subtitle = $hero_banner->hasField('field_hero_banner_subtitle')
      ? ($hero_banner->get('field_hero_banner_subtitle')->value ?: NULL)
      : NULL;

    if ($hero_banner->hasField('field_hero_banner_image')
      && !$hero_banner->get('field_hero_banner_image')->isEmpty()) {
      $hero_image = $hero_banner->get('field_hero_banner_image')->entity;

      if ($hero_image?->getEntityTypeId() === 'media'
        && $hero_image->hasField('field_media_image')
        && !$hero_image->get('field_media_image')->isEmpty()) {
        $image_item = $hero_image->get('field_media_image')->first();
        $file = $image_item?->entity;

        $image_title = $image_item?->title ?: NULL;
        $image_alt = $image_item?->alt ?: NULL;

        if ($file) {
          $image_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());

          $responsive_image_style = ResponsiveImageStyle::load('hello_hero_banner');
          if ($responsive_image_style) {
            $fallback_image_style_id = $responsive_image_style->getFallbackImageStyle();
            if ($fallback_image_style_id) {
              $fallback_image_style = ImageStyle::load($fallback_image_style_id);
              if ($fallback_image_style) {
                $fallback_dimensions = [
                  'width' => $image_item?->width,
                  'height' => $image_item?->height,
                ];
                $fallback_image_style->transformDimensions($fallback_dimensions, $file->getFileUri());

                $fallback_image = [
                  'image_style' => $fallback_image_style_id,
                  'image_url' => $fallback_image_style->buildUrl($file->getFileUri()),
                  'width' => $fallback_dimensions['width'] ?? NULL,
                  'height' => $fallback_dimensions['height'] ?? NULL,
                ];
              }
            }

            foreach ($responsive_image_style->getImageStyleMappings() as $mapping) {
              $image_style_id = $mapping['image_mapping_type'] === 'image_style'
                ? ($mapping['image_mapping'] ?? NULL)
                : NULL;

              if (!$image_style_id) {
                continue;
              }

              $image_style = ImageStyle::load($image_style_id);
              if (!$image_style) {
                continue;
              }

              $dimensions = [
                'width' => $image_item?->width,
                'height' => $image_item?->height,
              ];
              $image_style->transformDimensions($dimensions, $file->getFileUri());

              $responsive_images[] = [
                'breakpoint' => $mapping['breakpoint_id'] ?? NULL,
                'multiplier' => $mapping['multiplier'] ?? NULL,
                'image_style' => $image_style_id,
                'image_url' => $image_style->buildUrl($file->getFileUri()),
                'width' => $dimensions['width'] ?? NULL,
                'height' => $dimensions['height'] ?? NULL,
              ];
            }
          }
        }
      }
    }

    return [
      'image_url' => $image_url,
      'image_title' => $image_title,
      'image_alt' => $image_alt,
      'hero_title' => $hero_title,
      'hero_subtitle' => $hero_subtitle,
      'responsive_image_style' => 'hello_hero_banner',
      'responsive_images' => $responsive_images,
      'fallback_image' => $fallback_image,
    ];
  }

}
