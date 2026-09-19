<?php

declare(strict_types=1);

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!function_exists('pSQL')) {
    function pSQL(?string $value, bool $htmlOk = false): string
    {
        return addslashes($value ?? '');
    }
}

if (!class_exists('Validate')) {
    final class Validate
    {
        /** @param mixed $value */
        public static function isDate($value): bool
        {
            if (!is_string($value) || !preg_match(
                '/^(\d{4})-(\d{1,2})-(\d{1,2})(?: \d{2}:\d{2}:\d{2})?$/D',
                $value,
                $matches
            )) {
                return false;
            }

            return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
        }
    }
}

if (!class_exists('DbQuery')) {
    final class DbQuery
    {
        /** @var list<string> */
        private array $parts = [];

        public function select(string $columns): self
        {
            $this->parts[] = 'SELECT ' . $columns;

            return $this;
        }

        public function from(string $table): self
        {
            $this->parts[] = 'FROM ' . $table;

            return $this;
        }

        public function where(string $condition): self
        {
            $this->parts[] = 'WHERE ' . $condition;

            return $this;
        }

        public function __toString(): string
        {
            return implode(' ', $this->parts);
        }
    }
}

final class KfaDeliveryTimeTestDb
{
    /** @var list<mixed> */
    public array $getValueResults = [];

    /** @var list<list<array<string, mixed>>|false> */
    public array $executeSResults = [];

    /** @var list<array<string, mixed>|false> */
    public array $getRowResults = [];

    /** @var list<bool> */
    public array $insertResults = [];

    /** @var list<bool> */
    public array $updateResults = [];

    /** @var list<bool> */
    public array $deleteResults = [];

    /** @var list<bool> */
    public array $executeResults = [];

    /** @var list<string> */
    public array $getValueQueries = [];

    /** @var list<bool> */
    public array $getValueUseCacheArgs = [];

    /** @var list<string> */
    public array $executeSQueries = [];

    /** @var list<bool> */
    public array $executeSUseCacheArgs = [];

    /** @var list<string> */
    public array $getRowQueries = [];

    /** @var list<array{table: string, data: array<string, mixed>, null_values: bool}> */
    public array $inserts = [];

    /** @var list<array{table: string, data: array<string, mixed>, where: string, null_values: bool}> */
    public array $updates = [];

    /** @var list<array{table: string, where: string}> */
    public array $deletes = [];

    /** @var list<string> */
    public array $executedSql = [];

    /** @var list<string> */
    public array $operations = [];

    public int $insertId = 0;

    public int $affectedRows = 1;

    public function reset(): void
    {
        $this->getValueResults = [];
        $this->executeSResults = [];
        $this->getRowResults = [];
        $this->insertResults = [];
        $this->updateResults = [];
        $this->deleteResults = [];
        $this->executeResults = [];
        $this->getValueQueries = [];
        $this->getValueUseCacheArgs = [];
        $this->executeSQueries = [];
        $this->executeSUseCacheArgs = [];
        $this->getRowQueries = [];
        $this->inserts = [];
        $this->updates = [];
        $this->deletes = [];
        $this->executedSql = [];
        $this->operations = [];
        $this->insertId = 0;
        $this->affectedRows = 1;
    }

    /** @param string|DbQuery $query
     *  @return mixed
     */
    public function getValue(string|DbQuery $query, bool $useCache = true): mixed
    {
        $this->getValueQueries[] = (string) $query;
        $this->getValueUseCacheArgs[] = $useCache;

        return array_shift($this->getValueResults);
    }

    /** @return list<array<string, mixed>>|false */
    public function executeS(string $query, bool $array = true, bool $useCache = true): array|false
    {
        $this->executeSQueries[] = $query;
        $this->executeSUseCacheArgs[] = $useCache;

        return array_shift($this->executeSResults) ?? [];
    }

    /** @return array<string, mixed>|false */
    public function getRow(string|DbQuery $query, bool $useCache = true): array|false
    {
        $this->getRowQueries[] = (string) $query;

        return array_shift($this->getRowResults) ?? false;
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data, bool $nullValues = false): bool
    {
        $this->operations[] = 'insert:' . $table;
        $this->inserts[] = [
            'table' => $table,
            'data' => $data,
            'null_values' => $nullValues,
        ];

        return array_shift($this->insertResults) ?? true;
    }

    /** @param array<string, mixed> $data */
    public function update(
        string $table,
        array $data,
        string $where = '',
        int $limit = 0,
        bool $nullValues = false
    ): bool {
        $this->operations[] = 'update:' . $table;
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'null_values' => $nullValues,
        ];

        return array_shift($this->updateResults) ?? true;
    }

    public function delete(string $table, string $where = ''): bool
    {
        $this->operations[] = 'delete:' . $table;
        $this->deletes[] = [
            'table' => $table,
            'where' => $where,
        ];

        return array_shift($this->deleteResults) ?? true;
    }

    public function execute(string $sql): bool
    {
        $this->operations[] = 'execute:' . trim($sql);
        $this->executedSql[] = $sql;

        return array_shift($this->executeResults) ?? true;
    }

    public function Insert_ID(): int
    {
        return $this->insertId;
    }

    public function Affected_Rows(): int
    {
        return $this->affectedRows;
    }
}

if (!class_exists('Db')) {
    final class Db
    {
        public static ?KfaDeliveryTimeTestDb $instance = null;

        public static function getInstance(bool $master = true): KfaDeliveryTimeTestDb
        {
            if (self::$instance === null) {
                self::$instance = new KfaDeliveryTimeTestDb();
            }

            return self::$instance;
        }
    }
}

if (!class_exists('KfaDeliveryTime')) {
    class KfaDeliveryTime
    {
        public const ORDER_CHECKING_BY_VALIDITY = 0;
        public const ORDER_CHECKING_BY_STATE = 1;

        /** @var array<string, mixed> */
        public array $configuration = [
            'ORDER_CHECKING' => self::ORDER_CHECKING_BY_VALIDITY,
            'ORDER_CHECKING_BY_CARRIER' => false,
        ];

        public static function logCalculations(string $message): void
        {
        }

        /** @return mixed */
        public function getConf(string $key): mixed
        {
            return $this->configuration[$key] ?? false;
        }
    }
}

if (!class_exists('Configuration')) {
    final class Configuration
    {
        /** @return mixed */
        public static function get(string $key): mixed
        {
            return $key === 'PS_CURRENCY_DEFAULT' ? 1 : false;
        }
    }
}

if (!class_exists('Tools')) {
    final class Tools
    {
        public static function convertPrice(float $amount, int $currencyId, bool $toCurrency = true): float
        {
            return $amount;
        }
    }
}

if (!class_exists('KfaDeliveryTimeHistory')) {
    final class KfaDeliveryTimeHistory
    {
        /** @var list<array{id_cart: int, id_employee: int, value: string}> */
        public static array $entries = [];
        public static bool $result = true;

        public static function addToHistory(int $idCart, int $idEmployee, string $value): bool
        {
            self::$entries[] = [
                'id_cart' => $idCart,
                'id_employee' => $idEmployee,
                'value' => $value,
            ];

            return self::$result;
        }
    }
}

if (!class_exists('Context')) {
    final class Context
    {
        private static ?self $instance = null;

        /** @var object{id: int} */
        public object $employee;

        private function __construct()
        {
            $this->employee = (object) ['id' => 0];
        }

        public static function getContext(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }
    }
}

require_once dirname(__DIR__) . '/classes/KfaDeliveryTimeCache.php';
require_once dirname(__DIR__) . '/classes/KfaDeliveryTimeCart.php';
require_once dirname(__DIR__) . '/classes/KfaDeliveryTimeCapacityHelper.php';
