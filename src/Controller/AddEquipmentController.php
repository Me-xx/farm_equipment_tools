<?php

namespace Drupal\farm_equipment_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\asset\Entity\Asset;
use Drupal\log\Entity\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AddEquipmentController extends ControllerBase {

  public function add($asset) {
    // Neues Equipment-Asset erstellen.
    $equipment = Asset::create([
      'type' => 'equipment',
      'name' => 'Neues Equipment',
    ]);
    $equipment->save();

    // Einlagerungs-Log erstellen.
    $log = Log::create([
      'type' => 'activity',
      'name' => 'Einlagern',
      'is_movement' => TRUE,
      'location' => $asset,
      'asset' => [$equipment->id()],
    ]);
    $log->save();

    $this->messenger()->addStatus($this->t('Neues Equipment erstellt und eingelagert.'));
    return new RedirectResponse("/asset/{$asset}/assets");
  }
}
