<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmOrderRepository
{
    private $context;
    private $allowedShopIds;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->allowedShopIds = array_map('intval', Shop::getContextListShopID());

        if (!$this->allowedShopIds && isset($context->shop->id)) {
            $this->allowedShopIds = array((int) $context->shop->id);
        }
    }

    public function getOrders(array $filters, $page, $limit, $sort, $direction)
    {
        $page = max(1, (int) $page);
        $limit = max(10, min(100, (int) $limit));
        $offset = ($page - 1) * $limit;
        $orderBy = $this->getOrderBy($sort, $direction);

        $sql = $this->getSelectSql() . $this->buildWhere($filters) .
            ' ORDER BY ' . $orderBy .
            ' LIMIT ' . $offset . ', ' . $limit;

        return Db::getInstance()->executeS($sql);
    }

    public function countOrders(array $filters)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(DISTINCT o.id_order)
             FROM `' . _DB_PREFIX_ . 'orders` o
             INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                ON osl.id_order_state = o.current_state
               AND osl.id_lang = ' . (int) $this->context->language->id . '
             LEFT JOIN `' . _DB_PREFIX_ . 'carrier` ca ON ca.id_carrier = o.id_carrier
             LEFT JOIN `' . _DB_PREFIX_ . 'wilden_manager_order_note` wn ON wn.id_order = o.id_order' .
            $this->buildWhere($filters)
        );
    }

    public function getOrdersForExport(array $filters, $sort, $direction, $maxRows = 10000)
    {
        return Db::getInstance()->executeS(
            $this->getSelectSql() . $this->buildWhere($filters) .
            ' ORDER BY ' . $this->getOrderBy($sort, $direction) .
            ' LIMIT ' . max(1, (int) $maxRows)
        );
    }

    public function getOrderSummaries(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return array();
        }

        return Db::getInstance()->executeS(
            'SELECT o.id_order, o.reference, o.id_shop, o.current_state,
                    osl.name AS state_name, o.date_add,
                    CONCAT(c.firstname, CHAR(32), c.lastname) AS customer
             FROM `' . _DB_PREFIX_ . 'orders` o
             INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                ON osl.id_order_state = o.current_state
               AND osl.id_lang = ' . (int) $this->context->language->id . '
             WHERE o.id_order IN (' . implode(',', $ids) . ')
               AND o.id_shop IN (' . $this->getAllowedShopSql() . ')
             ORDER BY o.date_add ASC, o.id_order ASC'
        );
    }

    public function isOrderAccessible($idOrder)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'orders`
             WHERE id_order = ' . (int) $idOrder . '
               AND id_shop IN (' . $this->getAllowedShopSql() . ')'
        );
    }

    private function getSelectSql()
    {
        return 'SELECT o.id_order, o.reference, o.date_add, o.total_paid_tax_incl,
                       o.current_state, o.payment, o.id_currency,
                       osl.name AS state_name, osl.color AS state_color,
                       CONCAT(c.firstname, CHAR(32), c.lastname) AS customer,
                       c.email, ca.name AS carrier_name, wn.note
                FROM `' . _DB_PREFIX_ . 'orders` o
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
                LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                   ON osl.id_order_state = o.current_state
                  AND osl.id_lang = ' . (int) $this->context->language->id . '
                LEFT JOIN `' . _DB_PREFIX_ . 'carrier` ca ON ca.id_carrier = o.id_carrier
                LEFT JOIN `' . _DB_PREFIX_ . 'wilden_manager_order_note` wn ON wn.id_order = o.id_order';
    }

    private function buildWhere(array $filters)
    {
        $where = array('o.id_shop IN (' . $this->getAllowedShopSql() . ')');

        if (!empty($filters['id_order'])) {
            $where[] = 'o.id_order = ' . (int) $filters['id_order'];
        }
        if (!empty($filters['reference'])) {
            $where[] = 'o.reference LIKE ' . $this->sqlLike($filters['reference']);
        }
        if (!empty($filters['customer'])) {
            $customer = $this->sqlLike($filters['customer']);
            $where[] = '(CONCAT(c.firstname, CHAR(32), c.lastname) LIKE ' . $customer . '
                         OR c.email LIKE ' . $customer . ')';
        }
        if (!empty($filters['id_order_state'])) {
            $where[] = 'o.current_state = ' . (int) $filters['id_order_state'];
        }
        if (!empty($filters['payment'])) {
            $where[] = 'o.payment LIKE ' . $this->sqlLike($filters['payment']);
        }
        if (!empty($filters['carrier'])) {
            $where[] = 'ca.name LIKE ' . $this->sqlLike($filters['carrier']);
        }
        if (!empty($filters['note'])) {
            $where[] = 'wn.note LIKE ' . $this->sqlLike($filters['note']);
        }
        if (!empty($filters['date_from']) && Validate::isDate($filters['date_from'])) {
            $where[] = "o.date_add >= '" . pSQL($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && Validate::isDate($filters['date_to'])) {
            $where[] = "o.date_add <= '" . pSQL($filters['date_to']) . " 23:59:59'";
        }
        if ($filters['total_min'] !== '' && Validate::isPrice($filters['total_min'])) {
            $where[] = 'o.total_paid_tax_incl >= ' . (float) $filters['total_min'];
        }
        if ($filters['total_max'] !== '' && Validate::isPrice($filters['total_max'])) {
            $where[] = 'o.total_paid_tax_incl <= ' . (float) $filters['total_max'];
        }

        return ' WHERE ' . implode(' AND ', $where);
    }

    private function getOrderBy($sort, $direction)
    {
        $allowed = array(
            'id_order' => 'o.id_order',
            'reference' => 'o.reference',
            'customer' => 'customer',
            'total' => 'o.total_paid_tax_incl',
            'state' => 'state_name',
            'payment' => 'o.payment',
            'date_add' => 'o.date_add',
        );

        $column = isset($allowed[$sort]) ? $allowed[$sort] : 'o.date_add';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', o.id_order ' . $direction;
    }

    private function getAllowedShopSql()
    {
        return implode(',', $this->allowedShopIds ?: array(0));
    }

    private function sqlLike($value)
    {
        return "'%" . pSQL((string) $value) . "%'";
    }
}
