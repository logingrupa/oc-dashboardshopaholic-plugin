<div class="widget-body lg-new-orders">
    <h3 class="widget-title" v-text="widget.configuration.title"></h3>

    <p v-if="loading && !customData" class="lg-new-orders-note"><?= e(trans('logingrupa.dashboardshopaholic::lang.widget.loading')) ?></p>

    <template v-if="customData">
        <p v-if="!customData.orders.length" class="lg-new-orders-note"><?= e(trans('logingrupa.dashboardshopaholic::lang.widget.no_orders')) ?></p>

        <table v-else class="lg-new-orders-table">
            <tbody>
                <tr v-for="order in customData.orders" :key="order.id">
                    <td>
                        <a :href="order.url" class="lg-new-orders-number" v-text="'#' + order.order_number"></a>
                        <div class="lg-new-orders-muted" v-text="order.created_at"></div>
                    </td>
                    <td>
                        <span v-text="order.customer_name"></span>
                        <div class="lg-new-orders-muted" v-text="order.email"></div>
                    </td>
                    <td class="lg-new-orders-right">
                        <span class="lg-new-orders-total" v-text="order.total"></span>
                        <div class="lg-new-orders-muted" v-text="order.payment_method"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </template>
</div>
