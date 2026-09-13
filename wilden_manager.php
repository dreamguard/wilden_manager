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
    const VERSION = '1.2.1';
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
        return array(
            'id_order' => $this->l('ID'),
            'reference' => $this->l('Reference'),
            'new' => $this->l('New client'),
            'country_name' => $this->l('Delivery'),
            'customer' => $this->l('Customer'),
            'company' => $this->l('Company'),
            'total_paid_tax_incl' => $this->l('Total'),
            'payment' => $this->l('Payment'),
            'osname' => $this->l('Status'),
            'date_add' => $this->l('Date'),
            'shop_name' => $this->l('Store'),
        );
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (empty($params['definition'])) {
            return;
        }

        $definition = $params['definition'];
        $columns = $definition->getColumns();
        $filters = $definition->getFilters();
        $available = $this->getNativeOrderColumns();
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
            $presentColumns = array_column($columns->toArray(), 'id');
            $position = in_array('orders_bulk', $presentColumns, true) ? 1 : 0;
            foreach ($selected as $columnId) {
                if (in_array($columnId, $presentColumns, true)) {
                    $columns->move($columnId, $position++);
                }
            }
        }
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
                'storageKey' => 'wilden_manager_order_columns_' .
                    (int) $this->context->employee->id . '_' . (int) $this->context->shop->id,
                'saveUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=saveNativeColumns',
                'title' => $this->l('Configure order columns'),
                'button' => $this->l('Columns'),
                'save' => $this->l('Save and reload'),
                'reset' => $this->l('Restore defaults'),
                'cancel' => $this->l('Cancel'),
                'error' => $this->l('The column preference could not be saved.'),
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
