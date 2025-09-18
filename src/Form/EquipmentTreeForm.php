<?php

namespace Drupal\farm_equipment_tools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asset\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\farm_equipment_tools\AssetHierarchyService;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class EquipmentTreeForm extends FormBase {

  protected AssetHierarchyService $hierarchyService;
  protected EntityTypeBundleInfoInterface $bundleInfo;
  protected PagerManagerInterface $pagerManager;
  protected ?Asset $asset = NULL;

  public function __construct(
    AssetHierarchyService $hierarchy_service,
    EntityTypeBundleInfoInterface $bundle_info,
    PagerManagerInterface $pager_manager
  ) {
    $this->hierarchyService = $hierarchy_service;
    $this->bundleInfo = $bundle_info;
    $this->pagerManager = $pager_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_equipment_tools.asset_hierarchy'),
      $container->get('entity_type.bundle.info'),
      $container->get('pager.manager')
    );
  }

  public function getFormId() {
    return 'farm_equipment_tools_equipment_tree_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, Asset $asset = NULL) {
    $this->asset = $asset;

    $form['#method'] = 'get';
    $form['#cache']['max-age'] = 0;

    $filter_default = $form_state->getValue('filter_label', $this->getRequest()->query->get('filter_label', ''));
    $sort_by_default = $form_state->getValue('sort_by', $this->getRequest()->query->get('sort_by', 'label'));
    $sort_order_default = $form_state->getValue('sort_order', $this->getRequest()->query->get('sort_order', 'asc'));
    $per_page_default = (int) $form_state->getValue('per_page', (int) $this->getRequest()->query->get('per_page', 50));

    $form['controls'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['equipment-tree-controls']],
    ];

    $form['controls']['filter_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter by label'),
      '#size' => 30,
      '#default_value' => $filter_default,
    ];

    $form['controls']['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        'label' => $this->t('Name'),
        'id' => $this->t('ID'),
        'type' => $this->t('Equipment type'),
        'location_label' => $this->t('Location'),
      ],
      '#default_value' => $sort_by_default,
    ];

    $form['controls']['sort_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Order'),
      '#options' => [
        'asc' => $this->t('Ascending'),
        'desc' => $this->t('Descending'),
      ],
      '#default_value' => $sort_order_default,
    ];

    $form['controls']['per_page'] = [
      '#type' => 'select',
      '#title' => $this->t('Items per page'),
      '#options' => [
        25 => 25,
        50 => 50,
        100 => 100,
        200 => 200,
      ],
      '#default_value' => $per_page_default,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    $data = $this->hierarchyService->getEquipmentForAssetTree($asset->id());
    $equipment_list = [];
    if (isset($data['#markup'])) {
      $decoded = json_decode(strip_tags($data['#markup']), TRUE);
      if (is_array($decoded)) {
        $equipment_list = $decoded;
      }
    }

    if ($filter_default !== '') {
      $equipment_list = array_filter($equipment_list, static function ($item) use ($filter_default) {
        return stripos((string) ($item['label'] ?? ''), $filter_default) !== FALSE;
      });
    }

    $equipment_ids = [];
    $location_ids = [];
    foreach ($equipment_list as $item) {
      if (!empty($item['id'])) {
        $equipment_ids[] = (int) $item['id'];
      }
      if (!empty($item['location'])) {
        $location_ids[] = (int) $item['location'];
      }
    }
    $equipment_entities = $equipment_ids ? Asset::loadMultiple(array_unique($equipment_ids)) : [];
    $location_entities = $location_ids ? Asset::loadMultiple(array_unique($location_ids)) : [];

    $enriched = [];
    foreach ($equipment_list as $item) {
      $id = isset($item['id']) ? (int) $item['id'] : NULL;
      $label = $item['label'] ?? '';
      $location_id = isset($item['location']) ? (int) $item['location'] : NULL;

      $type_label = '';
      if ($id && isset($equipment_entities[$id])) {
        $entity = $equipment_entities[$id];
        if ($entity->hasField('equipment_type') && !$entity->get('equipment_type')->isEmpty()) {
          $labels = [];
          foreach ($entity->get('equipment_type')->referencedEntities() as $ref) {
            $labels[] = $ref->label();
          }
          $type_label = implode(', ', $labels);
        }
      }

      $location_label = '';
      if ($location_id && isset($location_entities[$location_id])) {
        $location_label = $location_entities[$location_id]->label();
      }

      $enriched[] = [
        'id' => $id,
        'label' => $label,
        'type' => $type_label,
        'location_label' => $location_label,
      ];
    }

    $sort_by = in_array($sort_by_default, ['label', 'id', 'type', 'location_label'], TRUE) ? $sort_by_default : 'label';
    $direction = $sort_order_default === 'desc' ? -1 : 1;

    usort($enriched, static function ($a, $b) use ($sort_by, $direction) {
      $av = $a[$sort_by] ?? '';
      $bv = $b[$sort_by] ?? '';
      if ($sort_by === 'id') {
        return $direction * ((int) $av <=> (int) $bv);
      }
      return $direction * strnatcasecmp((string) $av, (string) $bv);
    });

    $per_page = max(1, (int) $per_page_default);
    $pager = $this->pagerManager->createPager(count($enriched), $per_page);
    $offset = $pager->getCurrentPage() * $per_page;
    $paged_items = array_slice($enriched, $offset, $per_page);

    $rows = [];
    foreach ($paged_items as $row) {
      $link = Link::fromTextAndUrl(
        (string) $row['label'],
        Url::fromRoute('entity.asset.canonical', ['asset' => $row['id']])
      )->toRenderable();

      $rows[] = [
        'id' => ['data' => $row['id']],
        'label' => ['data' => $link],
        'type' => ['data' => $row['type']],
        'location' => ['data' => $row['location_label']],
      ];
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'id' => $this->t('ID'),
        'label' => $this->t('Name'),
        'type' => $this->t('Equipment type'),
        'location' => $this->t('Location'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No equipment found.'),
    ];

    $params = $this->getRequest()->query->all();
    unset($params['page']);
    $form['pager'] = [
      '#type' => 'pager',
      '#quantity' => 5,
      '#parameters' => $params,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }
}
