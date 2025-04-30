<?php

declare(strict_types=1);

namespace Drupal\farm_integration\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Integrations controller.
 */
class IntegrationsController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * Constructs a new IntegrationsController.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree) {
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree')
    );
  }

  /**
   * The index of integrations.
   *
   * @return array
   *   Returns a render array.
   */
  public function index(): array {

    // Load all menu links below it.
    $parameters = new MenuTreeParameters();
    $parameters->setRoot('farm.integrations')->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load('', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    // Start cacheability for integrations list.
    $tree_access_cacheability = new CacheableMetadata();

    // Build list item for each integration item.
    $items = [];
    foreach ($tree as $element) {
      $tree_access_cacheability->addCacheableDependency($element->access);
      if ($element->access->isAllowed()) {
        $items[] = [
          'title' => $element->link->getTitle(),
          'description' => $element->link->getDescription(),
          'url' => $element->link->getUrlObject(),
        ];
      }
    }

    // Render items.
    if (!empty($items)) {
      $output = [
        '#theme' => 'admin_block_content',
        '#content' => $items,
      ];
    }
    else {
      $output = [
        '#markup' => $this->t('You do not have any integrations.'),
      ];
    }
    $tree_access_cacheability->applyTo($output);
    return $output;
  }

}
