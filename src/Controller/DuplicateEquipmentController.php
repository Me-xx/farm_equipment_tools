<?php

namespace Drupal\farm_equipment_tools\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Uuid\UuidInterface;

class DuplicateEquipmentController extends ControllerBase {

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  public function __construct(UuidInterface $uuid_service) {
    $this->uuidService = $uuid_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uuid')
    );
  }

  public function duplicate($asset) {
    $storage = $this->entityTypeManager()->getStorage('asset');
    $original = $storage->load($asset);

    if ($original) {
      $duplicate = $original->createDuplicate();
      $duplicate->enforceIsNew();
      $duplicate->set('uuid', $this->uuidService->generate());
      $duplicate->setName($original->label() . ' (Kopie)');
      $duplicate->save();

      $this->messenger()->addStatus($this->t('Ausrüstung wurde dupliziert.'));
      return $this->redirect('entity.asset.canonical', ['asset' => $duplicate->id()]);
    }

    throw new NotFoundHttpException();
  }
}
