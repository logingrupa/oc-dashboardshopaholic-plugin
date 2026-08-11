import WidgetBase from '../../../../../../../modules/dashboard/vuecomponents/dashboard/assets/js/widget-base.js';

export default {
    extends: WidgetBase,
    computed: {
        customData: function () {
            return this.fullWidgetData ? this.fullWidgetData.data : null;
        }
    },
    methods: {
        useCustomData: function () {
            return true;
        },

        makeDefaultConfigAndData: function () {
            if (!this.widget.configuration.title) {
                this.widget.configuration.title = 'New Orders';
            }

            if (!this.widget.configuration.ordersLimit) {
                this.widget.configuration.ordersLimit = '10';
            }
        },

        getSettingsConfiguration: function () {
            return [
                {
                    property: 'title',
                    title: 'Title',
                    type: 'string'
                },
                {
                    property: 'ordersLimit',
                    title: 'Orders to show (1-50)',
                    type: 'string',
                    validation: {
                        regex: {
                            pattern: '^[0-9]+$',
                            message: 'Enter a number'
                        }
                    }
                }
            ];
        }
    }
};
