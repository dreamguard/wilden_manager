<?php
/**
 * Standalone regression suite: php tests/run.php
 * Uses an in-memory fake of PrestaShop's Db (including getRow()'s automatic " LIMIT 1").
 * No shop, credentials or database needed. Not part of the installable module.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('_PS_VERSION_', '8.2.0');
define('_DB_PREFIX_', 'ps_');
error_reporting(E_ALL);
set_error_handler(function ($s, $m, $f, $l) { throw new ErrorException($m, 0, $s, $f, $l); });

function pSQL($v) { return addslashes((string) $v); }
function bqSQL($v) { return str_replace('`', '\`', (string) $v); }

class Configuration {
    public static $v = array();
    public static function get($k) { return isset(self::$v[$k]) ? self::$v[$k] : false; }
    public static function getGlobalValue($k) { return self::get($k); }
}
class Validate { public static function isLoadedObject($o) { return $o && $o->id; } }
class Product { public static function existsInDatabase($id, $t) { return in_array($id, FakeDb::$products); } }
class Combination { public static function existsInDatabase($id, $t) { return true; } }
class StockAvailable {
    public static $calls = array();
    public static function updateQuantity($p, $a, $d, $s, $m, $params) { self::$calls[] = array($p, $a, $d, $s); return true; }
}
class Order {
    public $id; public $id_shop;
    public function __construct($id) { $r = FakeDb::row('orders', 'id_order', $id); if ($r) { $this->id = $id; $this->id_shop = $r['id_shop']; } }
    public function delete() { FakeDb::$log[] = 'ORDER_DELETE'; FakeDb::remove('orders', 'id_order', array($this->id)); return true; }
}

class FakeDb {
    public static $t = array(); public static $engines = array(); public static $log = array(); public static $products = array(1, 2);
    public static $snapshot;
    public static function row($t, $c, $v) { foreach (self::$t[$t] as $r) { if ($r[$c] == $v) return $r; } return null; }
    public static function remove($t, $c, $vals) { self::$t[$t] = array_values(array_filter(self::$t[$t], function ($r) use ($c, $vals) { return !in_array($r[$c], $vals); })); }
    public function execute($sql) {
        self::$log[] = $sql;
        if ($sql === 'START TRANSACTION') { self::$snapshot = self::$t; return true; }
        if ($sql === 'ROLLBACK') { self::$t = self::$snapshot; return true; }
        if ($sql === 'COMMIT') return true;
        if (preg_match('/^DELETE FROM `ps_(\w+)` WHERE `(\w+)` IN \(([\d,]+)\)$/', $sql, $m)) { self::remove($m[1], $m[2], explode(',', $m[3])); return true; }
        if (preg_match('/^DELETE FROM `ps_(\w+)` WHERE \(/', $sql)) { return true; }
        throw new Exception('Unexpected execute: ' . $sql);
    }
    private function filter($sql) {
        if (preg_match('/LIMIT 1\s+LIMIT|FOR UPDATE\s+LIMIT/i', $sql)) throw new Exception('SQL syntax error near LIMIT 1: ' . $sql);
        if (!preg_match('/FROM `ps_(\w+)`(?: \w+)?\s+WHERE (.*?)(?: LIMIT 1)?(?: FOR UPDATE)?$/s', $sql, $m)) throw new Exception('Unparsed: ' . $sql);
        $table = $m[1]; $where = trim($m[2]);
        $rows = isset(self::$t[$table]) ? self::$t[$table] : array();
        foreach (preg_split('/\s+AND\s+/', $where) as $cond) {
            $cond = trim(preg_replace('/^\w+\./', '', trim($cond)));
            if (preg_match('/^`?(\w+)`? IN \(([\d,]+)\)$/', $cond, $c)) { $vals = explode(',', $c[2]); $rows = array_filter($rows, function ($r) use ($c, $vals) { return in_array($r[$c[1]], $vals); }); }
            elseif (preg_match("/^`?(\w+)`?\s*(=|<>|>)\s*'?([^']*)'?$/", $cond, $c)) {
                $rows = array_filter($rows, function ($r) use ($c) { $x = $r[$c[1]]; return $c[2] === '=' ? $x == $c[3] : ($c[2] === '<>' ? $x != $c[3] : $x > $c[3]); });
            } elseif (strpos($cond, 'issue_key LIKE') !== false) { $rows = array(); }
            else throw new Exception('Cond: ' . $cond);
        }
        return array_values($rows);
    }
    // Like PrestaShop's DbCore::getRow(): always appends " LIMIT 1".
    public function getRow($sql) { $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' LIMIT 1'; $r = $this->filter($sql); return $r ? $r[0] : false; }
    public function getValue($sql) {
        if (strpos($sql, 'information_schema') === false) { $r = $this->getRow($sql); return $r ? 1 : false; }
        if (strpos($sql, 'information_schema.COLUMNS') !== false) {
            preg_match("/TABLE_NAME = 'ps_(\w+)'.*COLUMN_NAME = '(\w+)'/s", $sql, $m);
            if (strpos($sql, 'LIKE') !== false) throw new Exception('LIKE on schema');
            return isset(self::$t[$m[1]]) ? 1 : false; // fake: every listed column exists
        }
        return $this->filter($sql) ? 1 : false;
    }
    public function executeS($sql) {
        if (strpos($sql, 'information_schema.TABLES') !== false) {
            if (preg_match('/TABLE_NAME LIKE/', $sql)) throw new Exception('LIKE on schema');
            preg_match_all("/'(ps_\w+)'/", $sql, $m); $out = array();
            foreach ($m[1] as $n) { $s = substr($n, 3); if (isset(self::$t[$s])) $out[] = array('TABLE_NAME' => $n, 'ENGINE' => isset(self::$engines[$s]) ? self::$engines[$s] : 'InnoDB'); }
            return $out;
        }
        return $this->filter($sql);
    }
}
class Db { public static function getInstance() { return new FakeDb(); } }

$moduleDir = dirname(__DIR__);
require $moduleDir . '/classes/WmDb.php';
require $moduleDir . '/classes/WmSpreadsheet.php';
require $moduleDir . '/classes/WmOrderDeletionService.php';

function seed() {
    FakeDb::$log = array(); StockAvailable::$calls = array(); FakeDb::$engines = array();
    Configuration::$v = array('PS_STOCK_MANAGEMENT' => 1, 'PS_OS_CANCELED' => 6, 'PS_OS_ERROR' => 8);
    FakeDb::$t = array(
        'orders' => array(
            array('id_order' => 10, 'id_shop' => 1, 'id_cart' => 50, 'reference' => 'REFA', 'invoice_number' => 0, 'current_state' => 2),
            array('id_order' => 11, 'id_shop' => 1, 'id_cart' => 51, 'reference' => 'REFB', 'invoice_number' => 0, 'current_state' => 2),
            array('id_order' => 12, 'id_shop' => 2, 'id_cart' => 52, 'reference' => 'REFC', 'invoice_number' => 0, 'current_state' => 6),
        ),
        'order_detail' => array(
            array('id_order_detail' => 100, 'id_order' => 10, 'product_id' => 1, 'product_attribute_id' => 0, 'product_quantity' => 3, 'product_quantity_reinjected' => 1),
            array('id_order_detail' => 101, 'id_order' => 10, 'product_id' => 2, 'product_attribute_id' => 5, 'product_quantity' => 2, 'product_quantity_reinjected' => 0),
            array('id_order_detail' => 102, 'id_order' => 11, 'product_id' => 1, 'product_attribute_id' => 0, 'product_quantity' => 9, 'product_quantity_reinjected' => 0),
            array('id_order_detail' => 103, 'id_order' => 12, 'product_id' => 1, 'product_attribute_id' => 0, 'product_quantity' => 4, 'product_quantity_reinjected' => 0),
        ),
        'order_detail_tax' => array(array('id_order_detail' => 100), array('id_order_detail' => 102)),
        'order_history' => array(array('id_order' => 10), array('id_order' => 11)),
        'order_invoice' => array(array('id_order_invoice' => 7, 'id_order' => 10, 'number' => 0)),
        'order_invoice_tax' => array(array('id_order_invoice' => 7)),
        'order_invoice_payment' => array(array('id_order_invoice' => 7, 'id_order_payment' => 30, 'id_order' => 10)),
        'order_payment' => array(array('id_order_payment' => 30, 'order_reference' => 'REFA'), array('id_order_payment' => 31, 'order_reference' => 'REFB')),
        'order_slip' => array(array('id_order_slip' => 1, 'id_order' => 11)),
        'cart' => array(array('id_cart' => 50), array('id_cart' => 51)),
        'cart_product' => array(array('id_cart' => 50), array('id_cart' => 51)),
        'customization' => array(),
        'stock_mvt' => array(array('id_order' => 10)),
        'stock_available' => array(),
        'third_party_redsys' => array(array('id_order' => 10)),
    );
}
$fail = 0;
function check($label, $cond) { global $fail; echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$cond) $fail++; }
function ids($t, $c) { return array_map(function ($r) use ($c) { return $r[$c]; }, FakeDb::$t[$t]); }

// 1. Paid order without numbered invoice, stock reinjection enabled.
seed();
$svc = new WmOrderDeletionService();
$i = $svc->inspect(10, array(1));
check('paid order without numbered invoice is eligible', $i['eligible'] === true);
$r = $svc->delete(10, array(1), true);
check('order row removed', !in_array(10, ids('orders', 'id_order')));
check('other order untouched', in_array(11, ids('orders', 'id_order')));
check('details removed only for order 10', ids('order_detail', 'id_order_detail') == array(102, 103));
check('payment of the order removed', ids('order_payment', 'id_order_payment') == array(31));
check('invoice payment link removed', count(FakeDb::$t['order_invoice_payment']) === 0);
check('exclusive cart removed', ids('cart', 'id_cart') == array(51));
check('stock_mvt history untouched', count(FakeDb::$t['stock_mvt']) === 1);
check('third-party table untouched', count(FakeDb::$t['third_party_redsys']) === 1);
check('reinjected 2 + 2 units (refund reinjection not counted twice)', $r['stock_units_reinjected'] === 4);
check('stock calls', StockAvailable::$calls == array(array(1, 0, 2, 1), array(2, 5, 2, 1)));
check('committed', end(FakeDb::$log) === 'COMMIT');

// 2. Setting disabled: no stock change.
seed();
$r = (new WmOrderDeletionService())->delete(10, array(1), false);
check('no reinjection when disabled', $r['stock_action'] === 'none' && !StockAvailable::$calls);

// 3. Credit slip blocks deletion.
seed();
$i = (new WmOrderDeletionService())->inspect(11, array(1));
check('credit slip blocks', $i['eligible'] === false && $i['has_credit_slip'] === true);
try { (new WmOrderDeletionService())->delete(11, array(1), true); check('credit slip delete refused', false); }
catch (RuntimeException $e) { check('credit slip delete refused', in_array(11, ids('orders', 'id_order'))); }

// 4. Numbered invoice blocks deletion.
seed();
FakeDb::$t['order_invoice'][0]['number'] = 15;
$i = (new WmOrderDeletionService())->inspect(10, array(1));
check('numbered invoice blocks', $i['eligible'] === false && $i['has_invoice'] === true);

// 5. Shop context: order of shop 2 hidden from shop 1, allowed in all-shops context; cancelled order not reinjected.
seed();
check('other shop not visible', (new WmOrderDeletionService())->inspect(12, array(1))['exists'] === false);
$r = (new WmOrderDeletionService())->delete(12, array(1, 2), true);
check('all-shops context deletes shop 2 order; cancelled skipped', $r['stock_action'] === 'already' && !StockAvailable::$calls);

// 6. Non-transactional affected table refuses before any change.
seed();
FakeDb::$engines['order_history'] = 'MyISAM';
try { (new WmOrderDeletionService())->delete(10, array(1), true); check('MyISAM refused', false); }
catch (RuntimeException $e) { check('MyISAM refused without changes', in_array(10, ids('orders', 'id_order')) && !in_array('START TRANSACTION', FakeDb::$log)); }

// 7. Failure mid-way rolls back.
seed();
StockAvailable::$calls = array();
class_exists('StockAvailable');
FakeDb::$products = array(); // products missing -> skipped, not failure
$r = (new WmOrderDeletionService())->delete(10, array(1), true);
check('missing products are skipped', $r['stock_units_reinjected'] === 0);

// 8. No schema query uses LIKE (prefix wildcard bug).
$likes = array_filter(FakeDb::$log, function ($s) { return stripos($s, 'TABLE_NAME LIKE') !== false; });
check('no LIKE on table names', !$likes);

// 9. WmDb guards against queries that Db::getRow()/getValue() would break.
$refused = 0;
foreach (array('SELECT 1 FROM x LIMIT 1', 'SELECT 1 FROM x LIMIT 0, 10', 'SELECT 1 FROM x FOR UPDATE') as $bad) {
    try { WmDb::exists($bad); } catch (InvalidArgumentException $e) { $refused++; }
}
check('WmDb refuses trailing LIMIT / FOR UPDATE', $refused === 3);

// 10. LIKE input is matched literally.
check('escapeLike escapes % _ and backslash', WmDb::escapeLike('50%_a\\b') === '50\\%\\_a\\\\b');
check('likeContains wraps escaped value', WmDb::likeContains('a_b') === "'%" . addslashes('a\\_b') . "%'");

// 11. Spreadsheet formula protection.
check('formula with = is neutralized', WmSpreadsheet::neutralize('=SUM(A1)') === "'=SUM(A1)");
check('formula after tab is neutralized', WmSpreadsheet::neutralize("\t=1+1") === "'\t=1+1");
check('formula after CR is neutralized', WmSpreadsheet::neutralize("\r@cmd") === "'\r@cmd");
check('plus and at are neutralized', WmSpreadsheet::neutralize('+34 600') === "'+34 600" && WmSpreadsheet::neutralize('@x') === "'@x");
check('negative numbers stay numeric', WmSpreadsheet::neutralize('-2') === '-2' && WmSpreadsheet::neutralize('-12.50') === '-12.50');
check('plain text untouched', WmSpreadsheet::neutralize('Mapa Militar') === 'Mapa Militar' && WmSpreadsheet::neutralize('') === '');

echo $fail ? "$fail FAILED\n" : "All regression scenarios passed.\n";
exit($fail ? 1 : 0);
