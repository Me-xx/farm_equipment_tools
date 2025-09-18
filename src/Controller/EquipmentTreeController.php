<?php

namespace Drupal\farm_equipment_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\farm_equipment_tools\AssetHierarchyService;

class EquipmentTreeController extends ControllerBase {

  /**
   * @var \Drupal\farm_equipment_tools\AssetHierarchyService
   */
  protected $assetHierarchy;

  public function __construct(AssetHierarchyService $asset_hierarchy) {
    $this->assetHierarchy = $asset_hierarchy;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_equipment_tools.asset_hierarchy')
    );
  }

  /**
   * Gibt alle Equipments im Asset-Baum als JSON-Markup zurück.
   *
   * @param int $asset
   *   Die Start-Asset-ID.
   *
   * @return array
   *   Render-Array mit JSON-Ausgabe.
   */
  public function tree($asset) {
    return $this->assetHierarchy->getEquipmentForAssetTree($asset);
  }

}
