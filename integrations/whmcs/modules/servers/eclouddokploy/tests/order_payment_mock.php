<?php
declare(strict_types=1);

namespace WHMCS\Database {
    class FakePaymentQuery {
        private string $table;
        private array $filters = [];
        public function __construct(string $table) { $this->table = $table; }
        public function where(string $key, $value): self {
            $this->filters[$key] = $value;
            return $this;
        }
        public function first(array $fields): ?object {
            foreach (Capsule::$tables[$this->table] ?? [] as $item) {
                $okay = true;
                foreach ($this->filters as $key => $value) {
                    if (!isset($item[$key]) || (string) $item[$key] !== (string) $value) {
                        $okay = false; break;
                    }
                }
                if (!$okay) continue;
                return (object) array_intersect_key($item, array_fill_keys($fields, true));
            }
            return null;
        }
    }
    class Capsule {
        public static array $tables = [
            'tblhosting' => [
                ['id'=>310,'userid'=>25,'orderid'=>101],
                ['id'=>311,'userid'=>25,'orderid'=>102],
                ['id'=>312,'userid'=>26,'orderid'=>103],
                ['id'=>313,'userid'=>25,'orderid'=>0],
            ],
            'tblorders' => [
                ['id'=>101,'invoiceid'=>501],
                ['id'=>102,'invoiceid'=>502],
                ['id'=>103,'invoiceid'=>503],
            ],
            'tblinvoices' => [
                ['id'=>501,'userid'=>25,'status'=>'Paid'],
                ['id'=>502,'userid'=>25,'status'=>'Unpaid'],
                ['id'=>503,'userid'=>26,'status'=>'Paid'],
            ],
        ];
        public static function table(string $name): FakePaymentQuery {
            return new FakePaymentQuery($name);
        }
    }
}

namespace {
    define('WHMCS', true);
    require_once dirname(__DIR__) . '/eclouddokploy.php';

    function assertPayment(bool $value, string $label): void {
        if (!$value) throw new \RuntimeException('WHMCS payment mock failed: ' . $label);
    }

    assertPayment(eclouddokploy_order_invoice_paid(310,25), 'paid invoice accepted');
    assertPayment(!eclouddokploy_order_invoice_paid(311,25), 'unpaid invoice denied');
    assertPayment(!eclouddokploy_order_invoice_paid(312,25), 'other client cannot deploy');
    assertPayment(!eclouddokploy_order_invoice_paid(313,25), 'no order invoice denied');
    assertPayment(!eclouddokploy_order_invoice_paid(99,25), 'unknown service denied');

    $checkout = [
        'serviceid'=>311, 'userid'=>25, 'domain'=>'app.empresa.com',
        'configoption6'=>'pedido',
        'customfields'=>['Repositorio GitHub'=>'https://github.com/example/site.git'],
    ];
    // No network, secret or Dokploy request may execute for unpaid orders.
    assertPayment(eclouddokploy_order_auto_install($checkout, []) === 'success',
        'unpaid checkout only reserved for later installation');
    echo "WHMCS paid-order automation mock contracts passed\n";
}
