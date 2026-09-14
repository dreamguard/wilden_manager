<?php
/**
 * Wilden Manager
 *
 * @author    Wilden Militaria S.L.
 * @copyright 2026 Wilden Militaria S.L.
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/WmAuditLogger.php';
require_once __DIR__ . '/classes/WmOrderNote.php';
require_once __DIR__ . '/classes/WmSavedView.php';
require_once __DIR__ . '/classes/WmGridPreference.php';
require_once __DIR__ . '/classes/WmOrderRepository.php';
require_once __DIR__ . '/classes/WmBulkOrderService.php';

class Wilden_manager extends Module
{
    const VERSION = '1.4.0';
    const TAB_CLASS = 'AdminWildenManagerOrders';
    const MAX_BULK_ORDERS = 100;

    public function __construct()
    {
        $this->name = 'wilden_manager';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Wilden Militaria S.L.';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => '8.99.99');

        parent::__construct();

        $this->displayName = $this->l('Wilden Manager');
        $this->description = $this->l('Advanced and auditable order management for PrestaShop.');
        $this->confirmUninstall = $this->l('Remove Wilden Manager and all of its notes, saved views and audit records?');
    }

    public function install()
    {
        return parent::install()
            && $this->installDatabase()
            && $this->installTab()
            && $this->registerHook('actionOrderGridDefinitionModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallDatabase();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
    }

    public function getNativeOrderColumns()
    {
        $columns = array(
            'id_order' => $this->l('ID'),
            'reference' => $this->l('Reference'),
            'new' => $this->l('New client'),
            'country_name' => $this->l('Delivery'),
            'customer' => $this->l('Customer'),
            'customer_email' => $this->l('Customer email'),
        );

        if ((bool) Configuration::get('PS_B2B_ENABLE')) {
            $columns['company'] = $this->l('Company');
        }

        $columns['total_paid_tax_incl'] = $this->l('Total');
        $columns['payment'] = $this->l('Payment');
        $columns['shipping_method'] = $this->l('Shipping method');
        $columns['internal_note'] = $this->l('Internal note');
        $columns['osname'] = $this->l('Status');
        $columns['date_add'] = $this->l('Date');

        if (Shop::isFeatureActive()) {
            $columns['shop_name'] = $this->l('Store');
        }

        return $columns;
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (empty($params['definition'])) {
            return;
        }

        $definition = $params['definition'];
        $columns = $definition->getColumns();
        $filters = $definition->getFilters();

        $columns->addAfter(
            'customer',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('customer_email'))
                ->setName($this->l('Customer email'))
                ->setOptions(array('field' => 'customer_email'))
        );
        $columns->addAfter(
            'payment',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('shipping_method'))
                ->setName($this->l('Shipping method'))
                ->setOptions(array('field' => 'shipping_method'))
        );
        $columns->addAfter(
            'shipping_method',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('internal_note'))
                ->setName($this->l('Internal note'))
                ->setOptions(array('field' => 'internal_note'))
        );

        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'customer_email',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->l('Search email')),
                ))
                ->setAssociatedColumn('customer_email')
        );
        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'shipping_method',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->l('Search shipping method')),
                ))
                ->setAssociatedColumn('shipping_method')
        );
        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'internal_note',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->l('Search internal note')),
                ))
                ->setAssociatedColumn('internal_note')
        );

        $presentColumns = $this->getGridCollectionIds($columns);
        $available = array_intersect_key(
            $this->getNativeOrderColumns(),
            array_flip($presentColumns)
        );
        $selected = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );

        if (!$selected) {
            $selected = array_keys($available);
        }

        $selected = array_values(array_intersect($selected, array_keys($available)));
        foreach (array_keys($available) as $columnId) {
            if (!in_array($columnId, $selected, true)) {
                $columns->remove($columnId);
                $filters->remove($columnId);
            }
        }

        if (method_exists($columns, 'move')) {
            $position = in_array('orders_bulk', $presentColumns, true) ? 1 : 0;
            foreach ($selected as $columnId) {
                if (in_array($columnId, $presentColumns, true)) {
                    $columns->move($columnId, $position++);
                }
            }
        }
    }

    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        if (empty($params['search_query_builder']) || empty($params['count_query_builder'])) {
            return;
        }

        $searchQueryBuilder = $params['search_query_builder'];
        $countQueryBuilder = $params['count_query_builder'];
        $searchCriteria = isset($params['search_criteria']) ? $params['search_criteria'] : null;

        $searchQueryBuilder
            ->addSelect('cu.email AS customer_email')
            ->leftJoin(
                'o',
                _DB_PREFIX_ . 'carrier',
                'wm_shipping_carrier',
                'o.id_carrier = wm_shipping_carrier.id_carrier'
            )
            ->addSelect('wm_shipping_carrier.name AS shipping_method')
            ->leftJoin(
                'o',
                _DB_PREFIX_ . 'wilden_manager_order_note',
                'wm_order_note',
                'o.id_order = wm_order_note.id_order'
            )
            ->addSelect("COALESCE(wm_order_note.note, '') AS internal_note");
        $countQueryBuilder->leftJoin(
            'o',
            _DB_PREFIX_ . 'carrier',
            'wm_shipping_carrier',
            'o.id_carrier = wm_shipping_carrier.id_carrier'
        )->leftJoin(
            'o',
            _DB_PREFIX_ . 'wilden_manager_order_note',
            'wm_order_note',
            'o.id_order = wm_order_note.id_order'
        );

        if (!$searchCriteria) {
            return;
        }

        $filters = $searchCriteria->getFilters();
        $this->applyNativeTextFilter(
            $searchQueryBuilder,
            $countQueryBuilder,
            $filters,
            'customer_email',
            'cu.email'
        );
        $this->applyNativeTextFilter(
            $searchQueryBuilder,
            $countQueryBuilder,
            $filters,
            'shipping_method',
            'wm_shipping_carrier.name'
        );
        $this->applyNativeTextFilter(
            $searchQueryBuilder,
            $countQueryBuilder,
            $filters,
            'internal_note',
            'wm_order_note.note'
        );

        if ($searchCriteria->getOrderBy() === 'customer_email') {
            $searchQueryBuilder->orderBy('cu.email', $searchCriteria->getOrderWay());
        } elseif ($searchCriteria->getOrderBy() === 'shipping_method') {
            $searchQueryBuilder->orderBy('wm_shipping_carrier.name', $searchCriteria->getOrderWay());
        } elseif ($searchCriteria->getOrderBy() === 'internal_note') {
            $searchQueryBuilder->orderBy('wm_order_note.note', $searchCriteria->getOrderWay());
        }
    }

    private function applyNativeTextFilter(
        $searchQueryBuilder,
        $countQueryBuilder,
        array $filters,
        $filterName,
        $field
    ) {
        if (!isset($filters[$filterName]) || trim((string) $filters[$filterName]) === '') {
            return;
        }

        $parameter = 'wm_' . $filterName;
        $value = '%' . trim((string) $filters[$filterName]) . '%';
        foreach (array($searchQueryBuilder, $countQueryBuilder) as $queryBuilder) {
            $queryBuilder
                ->andWhere($field . ' LIKE :' . $parameter)
                ->setParameter($parameter, $value);
        }
    }

    private function getGridCollectionIds($collection)
    {
        $ids = array();
        foreach ($collection->toArray() as $key => $item) {
            if (is_object($item) && method_exists($item, 'getId')) {
                $ids[] = (string) $item->getId();
            } elseif (is_array($item) && isset($item['id'])) {
                $ids[] = (string) $item['id'];
            } elseif (is_string($key)) {
                $ids[] = $key;
            }
        }

        return array_values(array_unique($ids));
    }

    public function hookDisplayBackOfficeHeader()
    {
        $available = $this->getNativeOrderColumns();
        $selected = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );
        if (!$selected) {
            $selected = array_keys($available);
        }

        $columnOptions = array();
        foreach ($available as $id => $label) {
            $columnOptions[] = array('id' => $id, 'label' => $label);
        }

        $this->context->controller->addJS($this->_path . 'views/js/native-order-columns.js');
        $this->context->controller->addCSS($this->_path . 'views/css/native-order-columns.css');
        Media::addJsDef(array(
            'wildenManagerNativeColumns' => array(
                'columns' => $columnOptions,
                'selected' => array_values($selected),
                'storageKey' => 'wilden_manager_order_columns_' . self::VERSION . '_' .
                    (int) $this->context->employee->id . '_' . (int) $this->context->shop->id,
                'saveUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=saveNativeColumns',
                'noteGetUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=getOrderNote',
                'noteSaveUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=saveOrderNote',
                'title' => $this->l('Configure order columns'),
                'button' => $this->l('Columns'),
                'save' => $this->l('Save and reload'),
                'reset' => $this->l('Restore defaults'),
                'cancel' => $this->l('Cancel'),
                'error' => $this->l('The column preference could not be saved.'),
                'noteTitle' => $this->l('Internal note'),
                'noteHelp' => $this->l('Only employees with edit permission can modify this note. Maximum 5,000 characters.'),
                'noteSave' => $this->l('Save note'),
                'noteLoading' => $this->l('Loading note...'),
                'noteSaved' => $this->l('The internal note was saved.'),
                'noteError' => $this->l('The internal note could not be loaded or saved.'),
                'noteReadOnly' => $this->l('You do not have permission to edit this note.'),
            ),
        ));
    }

    private function installDatabase()
    {
        $queries = include __DIR__ . '/sql/install.php';

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDatabase()
    {
        $queries = include __DIR__ . '/sql/uninstall.php';

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function installTab()
    {
        if ((int) Tab::getIdFromClassName(self::TAB_CLASS)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::TAB_CLASS;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        $tab->icon = 'manage_search';

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Wilden Manager';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        if (!$idTab) {
            return true;
        }

        return (bool) (new Tab($idTab))->delete();
    }
}
