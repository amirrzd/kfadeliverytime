<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Grid\Column\Type {
    final class DataColumn
    {
        /** @var array<string, mixed> */
        public array $options = [];

        public string $name = '';

        public function __construct(public readonly string $id)
        {
        }

        public function setName(string $name): self
        {
            $this->name = $name;

            return $this;
        }

        /** @param array<string, mixed> $options */
        public function setOptions(array $options): self
        {
            $this->options = $options;

            return $this;
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Grid\Filter {
    final class Filter
    {
        public string $associatedColumn = '';

        /** @var array<string, mixed> */
        public array $typeOptions = [];

        public function __construct(
            public readonly string $id,
            public readonly string $type
        ) {
        }

        public function setAssociatedColumn(string $associatedColumn): self
        {
            $this->associatedColumn = $associatedColumn;

            return $this;
        }

        /** @param array<string, mixed> $typeOptions */
        public function setTypeOptions(array $typeOptions): self
        {
            $this->typeOptions = $typeOptions;

            return $this;
        }
    }
}

namespace Symfony\Component\Form\Extension\Core\Type {
    final class TextType
    {
    }
}

namespace PrestaShopBundle\Form\Admin\Type {
    final class DateRangeType
    {
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    final class KfaDeliveryTimeOrderGridCollection
    {
        /** @var array<string, object> */
        public array $items = [];

        public function addAfter(string $columnId, object $item): void
        {
            $this->items[$item->id] = $item;
        }

        public function add(object $item): void
        {
            $this->items[$item->id] = $item;
        }
    }

    final class KfaDeliveryTimeOrderGridDefinition
    {
        public KfaDeliveryTimeOrderGridCollection $columns;

        public KfaDeliveryTimeOrderGridCollection $filters;

        public function __construct()
        {
            $this->columns = new KfaDeliveryTimeOrderGridCollection();
            $this->filters = new KfaDeliveryTimeOrderGridCollection();
        }

        public function getColumns(): KfaDeliveryTimeOrderGridCollection
        {
            return $this->columns;
        }

        public function getFilters(): KfaDeliveryTimeOrderGridCollection
        {
            return $this->filters;
        }
    }

    final class KfaDeliveryTimeOrderGridSearchCriteria
    {
        /** @param array<string, mixed> $filters */
        public function __construct(
            private readonly string $orderBy = 'id_order',
            private readonly string $orderWay = 'DESC',
            private readonly array $filters = []
        ) {
        }

        public function getOrderBy(): string
        {
            return $this->orderBy;
        }

        public function getOrderWay(): string
        {
            return $this->orderWay;
        }

        /** @return array<string, mixed> */
        public function getFilters(): array
        {
            return $this->filters;
        }
    }

    final class KfaDeliveryTimeOrderGridQueryBuilder
    {
        /** @var list<array{from: string, table: string, alias: string, condition: string}> */
        public array $joins = [];

        /** @var list<string> */
        public array $selects = [];

        /** @var list<array{field: string, direction: string}> */
        public array $orders = [];

        /** @var list<string> */
        public array $where = [];

        /** @var array<string, mixed> */
        public array $parameters = [];

        public function leftJoin(string $from, string $table, string $alias, string $condition): self
        {
            $this->joins[] = compact('from', 'table', 'alias', 'condition');

            return $this;
        }

        public function addSelect(string $select): self
        {
            $this->selects[] = $select;

            return $this;
        }

        public function orderBy(string $field, string $direction): self
        {
            $this->orders[] = compact('field', 'direction');

            return $this;
        }

        public function andWhere(string $where): self
        {
            $this->where[] = $where;

            return $this;
        }

        public function orWhere(string $where): self
        {
            $this->where[] = $where;

            return $this;
        }

        public function setParameter(string $name, mixed $value): self
        {
            $this->parameters[$name] = $value;

            return $this;
        }
    }

    if (!class_exists('KfaPersianDate')) {
        final class KfaPersianDate
        {
            public static function isSolarDate(string $date): bool
            {
                return str_starts_with($date, '1405-');
            }

            public static function stoG(string $date): \DateTimeImmutable
            {
                if ($date === '1405-05-30') {
                    return new \DateTimeImmutable('2026-08-21');
                }

                if ($date === '1405-05-31') {
                    return new \DateTimeImmutable('2026-08-22');
                }

                throw new \InvalidArgumentException('Unexpected Solar date in test.');
            }
        }
    }

    require_once dirname(__DIR__) . '/classes/KfaDeliveryTimeOrderListing.php';

    final class OrderGridBehaviorTest extends TestCase
    {
        protected function setUp(): void
        {
            Db::getInstance()->reset();
        }

        public function testDeliveryTimeColumnRemainsVisibleButCannotBeSorted(): void
        {
            $definition = new KfaDeliveryTimeOrderGridDefinition();

            (new KfaDeliveryTimeOrderListing())->hookActionOrderGridDefinitionModifier(
                ['definition' => $definition],
                true,
                false
            );

            self::assertArrayHasKey('deliverytime', $definition->columns->items);
            self::assertFalse($definition->columns->items['deliverytime']->options['sortable']);
            self::assertArrayHasKey('deliverytime', $definition->filters->items);
        }

        public function testGridReadDoesNotExecuteQueriesOrWriteDisplayCache(): void
        {
            $searchQuery = new KfaDeliveryTimeOrderGridQueryBuilder();
            $countQuery = new KfaDeliveryTimeOrderGridQueryBuilder();

            (new KfaDeliveryTimeOrderListing())->hookActionOrderGridQueryBuilderModifier([
                'search_query_builder' => $searchQuery,
                'count_query_builder' => $countQuery,
                'search_criteria' => new KfaDeliveryTimeOrderGridSearchCriteria(),
            ], true, false);

            self::assertSame([], Db::getInstance()->executeSQueries);
            self::assertSame([], Db::getInstance()->executedSql);
            self::assertCount(1, $searchQuery->joins);
            self::assertSame('ps_kfadeliverytime_cart', $searchQuery->joins[0]['table']);
            self::assertSame(['kdt.`value` AS `deliverytime`'], $searchQuery->selects);
            self::assertSame([], $countQuery->joins);
            self::assertSame([], $countQuery->selects);
        }

        public function testPersistedDeliveryTimeSortFallsBackToDefaultOrder(): void
        {
            $searchQuery = new KfaDeliveryTimeOrderGridQueryBuilder();

            (new KfaDeliveryTimeOrderListing())->hookActionOrderGridQueryBuilderModifier([
                'search_query_builder' => $searchQuery,
                'count_query_builder' => new KfaDeliveryTimeOrderGridQueryBuilder(),
                'search_criteria' => new KfaDeliveryTimeOrderGridSearchCriteria('deliverytime', 'ASC'),
            ], true, false);

            self::assertSame([
                ['field' => 'o.`id_order`', 'direction' => 'DESC'],
            ], $searchQuery->orders);
        }

        public function testDeliveryDateFilterUsesNormalizedIndexedColumnForSearchAndCount(): void
        {
            $searchQuery = new KfaDeliveryTimeOrderGridQueryBuilder();
            $countQuery = new KfaDeliveryTimeOrderGridQueryBuilder();
            $criteria = new KfaDeliveryTimeOrderGridSearchCriteria(filters: [
                'deliverytime' => [
                    'from' => '1405-05-30',
                    'to' => '1405-05-31',
                ],
            ]);

            (new KfaDeliveryTimeOrderListing())->hookActionOrderGridQueryBuilderModifier([
                'search_query_builder' => $searchQuery,
                'count_query_builder' => $countQuery,
                'search_criteria' => $criteria,
            ], true, false);

            foreach ([$searchQuery, $countQuery] as $query) {
                self::assertCount(1, $query->joins);
                self::assertSame([
                    'kdt.`date_start` >= :deliverytime_from',
                    'kdt.`date_start` <= :deliverytime_to',
                ], $query->where);
                self::assertSame('2026-08-21 00:00:00', $query->parameters['deliverytime_from']);
                self::assertSame('2026-08-22 23:59:59', $query->parameters['deliverytime_to']);
                self::assertStringNotContainsString('SUBSTRING', implode(' ', $query->where));
            }

            self::assertSame([], $countQuery->selects);
        }

        public function testGregorianDeliveryDateFilterKeepsGregorianCalendarValue(): void
        {
            $searchQuery = new KfaDeliveryTimeOrderGridQueryBuilder();

            (new KfaDeliveryTimeOrderListing())->hookActionOrderGridQueryBuilderModifier([
                'search_query_builder' => $searchQuery,
                'count_query_builder' => new KfaDeliveryTimeOrderGridQueryBuilder(),
                'search_criteria' => new KfaDeliveryTimeOrderGridSearchCriteria(filters: [
                    'deliverytime' => ['from' => '2026-08-21'],
                ]),
            ], true, false);

            self::assertSame('2026-08-21 00:00:00', $searchQuery->parameters['deliverytime_from']);
        }
    }
}
