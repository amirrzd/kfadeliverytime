<?php

declare(strict_types=1);

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'test_');
}

final class KfaDeliveryTimeMariaDbAdapter
{
    private PDO $pdo;
    private int $affectedRows = 0;
    private ?string $failedDeleteTable = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function execute(string $sql): bool
    {
        $result = $this->pdo->exec($sql);
        $this->affectedRows = $result === false ? 0 : $result;

        return $result !== false;
    }

    public function delete(string $table, string $where = ''): bool
    {
        if ($this->failedDeleteTable === $table) {
            $this->failedDeleteTable = null;
            $this->affectedRows = 0;

            return false;
        }

        $whereSql = $where === '' ? '' : " WHERE $where";

        return $this->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '`' . $whereSql);
    }

    public function failNextDeleteFor(string $table): void
    {
        $this->failedDeleteTable = $table;
    }

    public function Affected_Rows(): int
    {
        return $this->affectedRows;
    }

    public function getValue(string $sql, bool $useCache = true): mixed
    {
        $statement = $this->pdo->query($sql);

        return $statement === false ? false : $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>>|false */
    public function executeS(string $sql, bool $array = true, bool $useCache = true): array|false
    {
        $statement = $this->pdo->query($sql);

        return $statement === false ? false : $statement->fetchAll();
    }

    public function escape(?string $value, bool $htmlOk = false): string
    {
        $quoted = $this->pdo->quote($value ?? '');

        return substr($quoted, 1, -1);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}

final class Db
{
    private static ?KfaDeliveryTimeMariaDbAdapter $instance = null;

    public static function getInstance(bool $master = true): KfaDeliveryTimeMariaDbAdapter
    {
        if (self::$instance === null) {
            $host = getenv('KFADELIVERYTIME_TEST_DB_HOST') ?: '127.0.0.1';
            $port = getenv('KFADELIVERYTIME_TEST_DB_PORT') ?: '3306';
            $database = getenv('KFADELIVERYTIME_TEST_DB_NAME') ?: 'kfadeliverytime_test';
            $user = getenv('KFADELIVERYTIME_TEST_DB_USER') ?: 'kfadeliverytime';
            $password = getenv('KFADELIVERYTIME_TEST_DB_PASSWORD') ?: 'kfadeliverytime';
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_FOUND_ROWS => false,
                ]
            );
            self::$instance = new KfaDeliveryTimeMariaDbAdapter($pdo);
        }

        return self::$instance;
    }
}

function pSQL(?string $value, bool $htmlOk = false): string
{
    return Db::getInstance()->escape($value, $htmlOk);
}

final class KfaDeliveryTime
{
    public static function logCalculations(string $message): void
    {
    }
}

final class KfaDeliveryTimeHistory
{
    /** @var list<array{id_cart: int, id_employee: int, value: string}> */
    public static array $entries = [];

    public static function addToHistory(int $idCart, int $idEmployee, string $value): bool
    {
        self::$entries[] = [
            'id_cart' => $idCart,
            'id_employee' => $idEmployee,
            'value' => $value,
        ];

        return true;
    }
}

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

require_once dirname(__DIR__, 2) . '/classes/KfaDeliveryTimeCart.php';
require_once dirname(__DIR__, 2) . '/classes/KfaDeliveryTimeCache.php';
require_once dirname(__DIR__, 2) . '/classes/KfaDeliveryTimeCacheSchema.php';
