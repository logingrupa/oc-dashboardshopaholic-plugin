<?php namespace Logingrupa\DashboardShopaholic\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Logingrupa\DashboardShopaholic\Classes\Helper\OrderStatWriter;
use Lovata\OrdersShopaholic\Classes\PromoMechanism\OrderPromoMechanismProcessor;
use Lovata\OrdersShopaholic\Models\Order;
use ReflectionProperty;
use Throwable;

/**
 * BackfillOrderStats recomputes the reporting row for every order.
 * Run once after install and after any manual order surgery.
 */
class BackfillOrderStats extends Command
{
    public const MAX_FAILURES = 20;

    /**
     * @var string signature
     */
    protected $signature = 'dashboardshopaholic:backfill {--chunk=200 : Orders per chunk (1-1000)}';

    /**
     * @var string description
     */
    protected $description = 'Rebuild the dashboard order stat table from existing orders';

    public function handle(): int
    {
        $iChunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $iWritten = 0;
        $arFailedIDList = [];

        Order::with(['order_position', 'order_promo_mechanism'])
            ->chunkById($iChunkSize, function ($obOrderList) use (&$iWritten, &$arFailedIDList): bool {
                foreach ($obOrderList as $obOrder) {
                    try {
                        OrderStatWriter::write($obOrder, false);
                        $iWritten++;
                    } catch (Throwable $obException) {
                        $arFailedIDList[] = (int) $obOrder->id;
                        Log::error('DashboardShopaholic backfill failed for order '.$obOrder->id.': '.$obException->getMessage());
                        $this->error('Order '.$obOrder->id.' failed: '.$obException->getMessage());

                        if (count($arFailedIDList) >= self::MAX_FAILURES) {
                            $this->error('Aborting: too many failures ('.self::MAX_FAILURES.')');
                            return false;
                        }
                    }
                }

                // The promo processor memoizes one instance per order id for the whole
                // process; a CLI loop over every order would grow without bound
                $this->resetPromoProcessorStore();
                $this->info('Processed '.$iWritten.' orders...');

                return true;
            });

        $this->info('Done. Written: '.$iWritten.', failed: '.count($arFailedIDList));
        if (!empty($arFailedIDList)) {
            $this->error('Failed order IDs: '.implode(', ', $arFailedIDList));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resetPromoProcessorStore(): void
    {
        $obReflectionProperty = new ReflectionProperty(OrderPromoMechanismProcessor::class, 'arProcessorStore');
        $obReflectionProperty->setAccessible(true);
        $obReflectionProperty->setValue(null, []);
    }
}
