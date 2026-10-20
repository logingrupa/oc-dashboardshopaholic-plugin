<?php

use Illuminate\Support\Facades\Schema;
use Logingrupa\DashboardShopaholic\Classes\Helper\OrderIdIndex;
use Logingrupa\DashboardShopaholic\Updates\AddOrderPositionsOrderIdIndex;
use Logingrupa\DashboardShopaholic\Updates\AddOrderPromoMechanismOrderIdIndex;
use October\Rain\Database\Schema\Blueprint;

/**
 * Both order_id index migrations share OrderIdIndex. setUp() already ran
 * each up() once against the SQLite stub tables.
 */
class OrderIdIndexTest extends BaseDashboardShopaholicTestCase
{
    public function testEachTableHasExactlyOneOrderIdIndex(): void
    {
        foreach ($this->makeMigrationList() as $obMigration) {
            $this->assertSame([$obMigration::INDEX], $this->getOrderIdIndexNameList($obMigration::TABLE));
        }
    }

    public function testSecondUpAddsNothing(): void
    {
        foreach ($this->makeMigrationList() as $obMigration) {
            $obMigration->up();

            $this->assertSame([$obMigration::INDEX], $this->getOrderIdIndexNameList($obMigration::TABLE));
        }
    }

    public function testUpKeepsAnExistingOrderIdIndexUnderAnotherName(): void
    {
        foreach ($this->makeMigrationList() as $obMigration) {
            $obMigration->down();
            Schema::table($obMigration::TABLE, function (Blueprint $obTable) use ($obMigration) {
                $obTable->index('order_id', $obMigration::TABLE.'_upstream_order_id');
            });

            $obMigration->up();

            $this->assertSame([$obMigration::TABLE.'_upstream_order_id'], $this->getOrderIdIndexNameList($obMigration::TABLE));
        }
    }

    public function testDownDropsTheIndexByNameAndRepeatsAsNoop(): void
    {
        foreach ($this->makeMigrationList() as $obMigration) {
            $obMigration->down();
            $obMigration->down();

            $this->assertSame([], $this->getOrderIdIndexNameList($obMigration::TABLE));
        }
    }

    public function testMissingTableIsNoopForUpAndDown(): void
    {
        foreach ($this->makeMigrationList() as $obMigration) {
            Schema::drop($obMigration::TABLE);

            $obMigration->up();
            $obMigration->down();

            $this->assertFalse(Schema::hasTable($obMigration::TABLE));
        }
    }

    public function testEmptyTableNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrderIdIndex::add('', AddOrderPromoMechanismOrderIdIndex::INDEX);
    }

    public function testEmptyIndexNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrderIdIndex::drop(AddOrderPromoMechanismOrderIdIndex::TABLE, '');
    }

    /**
     * @return list<AddOrderPositionsOrderIdIndex|AddOrderPromoMechanismOrderIdIndex>
     */
    private function makeMigrationList(): array
    {
        return [new AddOrderPositionsOrderIdIndex(), new AddOrderPromoMechanismOrderIdIndex()];
    }

    /**
     * Names of the indexes whose first column is order_id.
     * @return list<string>
     */
    private function getOrderIdIndexNameList(string $sTableName): array
    {
        $arNameList = [];
        foreach (Schema::getIndexes($sTableName) as $arIndex) {
            if (($arIndex['columns'][0] ?? null) === 'order_id') {
                $arNameList[] = $arIndex['name'];
            }
        }

        return $arNameList;
    }
}
