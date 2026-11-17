<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Corrects entries of an order's status history (ps_order_history).
 *
 * Only the record is corrected: the date/time and the employee of an entry can be
 * changed, and entries other than the most recent one can be deleted. The status
 * of an entry is never changed and the most recent entry always stays the most
 * recent one, so the order's current status does not change. No emails, stock
 * movements, invoices, payments or object hooks are triggered.
 *
 * "Most recent" follows PrestaShop's own ordering: date_add DESC, id_order_history DESC.
 */
class WmOrderHistoryService
{
    private $prefix;

    public function __construct()
    {
        $this->prefix = _DB_PREFIX_;
    }

    /**
     * @return array{order: array, entries: array, employees: array}
     */
    public function getHistory($idOrder, array $allowedShopIds, $idLang)
    {
        $order = $this->getOrder($idOrder, $allowedShopIds);
        $rows = Db::getInstance()->executeS(
            'SELECT oh.id_order_history, oh.id_order_state, oh.id_employee, oh.date_add,
                    osl.name AS state_name,
                    CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name
             FROM `' . $this->prefix . 'order_history` oh
             LEFT JOIN `' . $this->prefix . 'order_state_lang` osl
                ON osl.id_order_state = oh.id_order_state AND osl.id_lang = ' . (int) $idLang . '
             LEFT JOIN `' . $this->prefix . 'employee` e ON e.id_employee = oh.id_employee
             WHERE oh.id_order = ' . (int) $order['id_order'] . '
             ORDER BY oh.date_add DESC, oh.id_order_history DESC',
            true,
            false
        );

        $entries = array();
        foreach ((array) $rows as $index => $row) {
            $entries[] = array(
                'id_order_history' => (int) $row['id_order_history'],
                'id_order_state' => (int) $row['id_order_state'],
                'state_name' => (string) $row['state_name'],
                'id_employee' => (int) $row['id_employee'],
                'employee_name' => trim((string) $row['employee_name']),
                'date_add' => (string) $row['date_add'],
                'is_latest' => $index === 0,
                'can_delete' => $index > 0,
            );
        }

        return array(
            'order' => array(
                'id_order' => (int) $order['id_order'],
                'date_add' => (string) $order['date_add'],
            ),
            'entries' => $entries,
            'employees' => $this->getEmployees($entries),
        );
    }

    /**
     * @param string $dateTime "Y-m-d H:i:s", "Y-m-d H:i" or the "T"-separated variants
     * @param int $idEmployee 0 for "no employee" (customer or automatic change)
     *
     * @return array{before: array, after: array}
     */
    public function update($idOrder, $idOrderHistory, $dateTime, $idEmployee, array $allowedShopIds)
    {
        $date = $this->normalizeDate($dateTime);
        $idEmployee = (int) $idEmployee;
        if ($idEmployee < 0 || ($idEmployee > 0 && !WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'employee` WHERE id_employee = ' . $idEmployee
        ))) {
            throw new RuntimeException('The selected employee does not exist.');
        }

        return $this->inTransaction($idOrder, $allowedShopIds, function (array $order, array $entries) use ($idOrderHistory, $date, $idEmployee) {
            $position = $this->findPosition($entries, $idOrderHistory);
            $entry = $entries[$position];

            if (strtotime($date) > time()) {
                throw new RuntimeException('The date cannot be in the future.');
            }
            if (strcmp($date, (string) $order['date_add']) < 0) {
                throw new RuntimeException('The date cannot be earlier than the order date.');
            }
            // The most recent entry must remain the most recent one.
            if ($position === 0) {
                foreach (array_slice($entries, 1) as $other) {
                    if ($this->isAfter($other['date_add'], (int) $other['id_order_history'], $date, (int) $entry['id_order_history'])) {
                        throw new RuntimeException('The most recent status must keep a date later than the previous statuses.');
                    }
                }
            } elseif ($this->isAfter($date, (int) $entry['id_order_history'], $entries[0]['date_add'], (int) $entries[0]['id_order_history'])) {
                throw new RuntimeException('A previous status cannot have a date later than the most recent status.');
            }

            if (!Db::getInstance()->update(
                'order_history',
                array('date_add' => pSQL($date), 'id_employee' => $idEmployee),
                'id_order_history = ' . (int) $entry['id_order_history'] . ' AND id_order = ' . (int) $order['id_order'],
                1
            )) {
                throw new RuntimeException('The status entry could not be saved.');
            }

            return array(
                'before' => array(
                    'id_order_history' => (int) $entry['id_order_history'],
                    'id_order_state' => (int) $entry['id_order_state'],
                    'date_add' => (string) $entry['date_add'],
                    'id_employee' => (int) $entry['id_employee'],
                ),
                'after' => array(
                    'id_order_history' => (int) $entry['id_order_history'],
                    'id_order_state' => (int) $entry['id_order_state'],
                    'date_add' => $date,
                    'id_employee' => $idEmployee,
                ),
            );
        });
    }

    /**
     * @return array{deleted: array}
     */
    public function delete($idOrder, $idOrderHistory, array $allowedShopIds)
    {
        return $this->inTransaction($idOrder, $allowedShopIds, function (array $order, array $entries) use ($idOrderHistory) {
            $position = $this->findPosition($entries, $idOrderHistory);
            if ($position === 0) {
                throw new RuntimeException('The most recent status cannot be deleted.');
            }
            $entry = $entries[$position];
            if (!Db::getInstance()->delete(
                'order_history',
                'id_order_history = ' . (int) $entry['id_order_history'] . ' AND id_order = ' . (int) $order['id_order'],
                1
            )) {
                throw new RuntimeException('The status entry could not be deleted.');
            }

            return array(
                'deleted' => array(
                    'id_order_history' => (int) $entry['id_order_history'],
                    'id_order_state' => (int) $entry['id_order_state'],
                    'date_add' => (string) $entry['date_add'],
                    'id_employee' => (int) $entry['id_employee'],
                ),
            );
        });
    }

    /**
     * Locks the order, runs the change and verifies that the most recent entry did not change.
     */
    private function inTransaction($idOrder, array $allowedShopIds, callable $change)
    {
        $this->getOrder($idOrder, $allowedShopIds);
        $db = Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $order = WmDb::lockRow(
                'SELECT id_order, id_shop, date_add FROM `' . $this->prefix . 'orders`
                 WHERE id_order = ' . (int) $idOrder
            );
            if (!$order || !in_array((int) $order['id_shop'], array_map('intval', $allowedShopIds), true)) {
                throw new RuntimeException('Order not found in the active store context.');
            }
            $entries = $this->getEntriesForUpdate((int) $idOrder);
            if (!$entries) {
                throw new RuntimeException('The order has no status history.');
            }
            $latestBefore = (int) $entries[0]['id_order_history'];

            $result = $change($order, $entries);

            $after = $this->getEntriesForUpdate((int) $idOrder);
            if (!$after || (int) $after[0]['id_order_history'] !== $latestBefore) {
                throw new RuntimeException('The most recent status would change; the operation was cancelled.');
            }
            $db->execute('COMMIT');
        } catch (Exception $exception) {
            $db->execute('ROLLBACK');
            throw $exception;
        } catch (Throwable $throwable) {
            $db->execute('ROLLBACK');
            throw $throwable;
        }

        return $result;
    }

    private function getEntriesForUpdate($idOrder)
    {
        return (array) Db::getInstance()->executeS(
            'SELECT id_order_history, id_order_state, id_employee, date_add
             FROM `' . $this->prefix . 'order_history`
             WHERE id_order = ' . (int) $idOrder . '
             ORDER BY date_add DESC, id_order_history DESC
             FOR UPDATE',
            true,
            false
        );
    }

    private function findPosition(array $entries, $idOrderHistory)
    {
        foreach ($entries as $position => $entry) {
            if ((int) $entry['id_order_history'] === (int) $idOrderHistory) {
                return $position;
            }
        }
        throw new RuntimeException('The status entry does not belong to this order.');
    }

    /**
     * True when (dateA, idA) sorts before (dateB, idB) in PrestaShop's history order,
     * i.e. A would be displayed as more recent than B.
     */
    private function isAfter($dateA, $idA, $dateB, $idB)
    {
        $cmp = strcmp((string) $dateA, (string) $dateB);

        return $cmp > 0 || ($cmp === 0 && (int) $idA > (int) $idB);
    }

    private function normalizeDate($value)
    {
        $value = str_replace('T', ' ', trim((string) $value));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        $parsed = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new RuntimeException('Enter a valid date and time.');
        }

        return $value;
    }

    private function getOrder($idOrder, array $allowedShopIds)
    {
        $order = WmDb::fetchRow(
            'SELECT id_order, id_shop, date_add FROM `' . $this->prefix . 'orders`
             WHERE id_order = ' . (int) $idOrder
        );
        if (!$order || !in_array((int) $order['id_shop'], array_map('intval', $allowedShopIds), true)) {
            throw new RuntimeException('Order not found in the active store context.');
        }

        return $order;
    }

    /**
     * Active employees plus any employee already referenced by the history.
     */
    private function getEmployees(array $entries)
    {
        $referenced = array_filter(array_map(function ($entry) {
            return (int) $entry['id_employee'];
        }, $entries));
        $where = 'active = 1';
        if ($referenced) {
            $where .= ' OR id_employee IN (' . implode(',', array_unique($referenced)) . ')';
        }
        $rows = Db::getInstance()->executeS(
            'SELECT id_employee, firstname, lastname, active
             FROM `' . $this->prefix . 'employee`
             WHERE ' . $where . '
             ORDER BY firstname, lastname',
            true,
            false
        );
        $employees = array();
        foreach ((array) $rows as $row) {
            $employees[] = array(
                'id_employee' => (int) $row['id_employee'],
                'name' => trim($row['firstname'] . ' ' . $row['lastname']),
                'active' => (bool) $row['active'],
            );
        }

        return $employees;
    }
}
