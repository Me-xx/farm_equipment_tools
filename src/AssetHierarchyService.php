<?php

namespace Drupal\farm_equipment_tools;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farm_location\AssetLocationInterface;

class AssetHierarchyService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AssetLocationInterface $assetLocationService;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AssetLocationInterface $asset_location_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->assetLocationService = $asset_location_service;
  }

  /**
   * Holt alle Equipments, die in einem Land (inkl. aller Strukturen) lokalisiert sind.
   */
  public function getEquipmentForAssetTree($land_id) {
    // Alle Location-IDs sammeln
    $location_ids = [$land_id];
    $visited_ids = [];
    $this->collectChildAssets($land_id, $location_ids, $visited_ids);

    // IDs in Asset-Objekte laden
    $location_assets = $this->entityTypeManager
      ->getStorage('asset')
      ->loadMultiple($location_ids);

    // Assets an den Locations ermitteln
    $assets_at_locations = $this->assetLocationService
      ->getAssetsByLocation($location_assets);

    // Nur Equipments herausfiltern
    $equipments = array_filter($assets_at_locations, fn($asset) => $asset->bundle() === 'equipment');

    // Ausgabe vorbereiten
    $output = [];
    foreach ($equipments as $equipment) {
      $output[] = [
        'id' => $equipment->id(),
        'label' => $equipment->label(),
        'uuid' => $equipment->uuid(),
        'location' => $equipment->hasField('location') ? $equipment->get('location')->target_id : null,
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => '<pre>' . json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>',
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Rekursiv alle Child-Assets sammeln.
   */
  protected function collectChildAssets($parent_id, array &$collected_ids, array &$visited_ids) {
    if (in_array($parent_id, $visited_ids, true)) {
      return;
    }
    $visited_ids[] = $parent_id;

    $query = $this->entityTypeManager->getStorage('asset')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('parent.target_id', $parent_id);
    $child_ids = $query->execute();

    foreach ($child_ids as $child_id) {
      $collected_ids[] = $child_id;
      $this->collectChildAssets($child_id, $collected_ids, $visited_ids);
    }
  }

}
