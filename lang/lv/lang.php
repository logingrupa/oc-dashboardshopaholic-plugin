<?php return [
    'plugin' => [
        'name' => 'Shopaholic vadības panelis',
        'description' => 'Veikala vadības panelis: pasūtījumu grupas, apgrozījums, peļņas metrikas, jauno pasūtījumu logrīks',
    ],
    'settings' => [
        'label' => 'Vadības panelis',
        'description' => 'Pašizmaksas cenu tips peļņas metrikai',
        'cost_price_type' => 'Pašizmaksas cenu tips',
        'cost_price_type_comment' => 'Cenu tips ar iepirkuma (pašizmaksas) cenām, piem. Vairum. Peļņa = pasūtījuma ieņēmumi bez PVN mīnus šī cena par katru pozīciju. Tukšs = peļņas metrika panelī netiek rādīta. To, vai šis cenu tips glabā cenas ar vai bez PVN, atzīmē pašā cenu tipā (Shopaholic > Cenu tipi).',
        'cost_price_type_empty' => '-- nav, peļņas metrika izslēgta --',
    ],
    'permission' => [
        'tab' => 'Shopaholic vadības panelis',
        'access_dashboard' => 'Piekļuve veikala vadības panelim',
        'view_profit' => 'Skatīt peļņas metrikas',
    ],
    'field' => [
        'price_includes_vat' => 'Cenas ietver PVN (bruto)',
        'price_includes_vat_comment' => 'Izslēgts = cenas glabātas bez PVN (neto). Izmanto paneļa peļņas metrika, ja šis cenu tips ir pašizmaksas bāze',
        'price_includes_vat_column' => 'Ar PVN',
    ],
    'dimension' => [
        'date' => 'Datums',
        'status' => 'Pasūtījuma statuss',
        'payment_method' => 'Apmaksas veids',
    ],
    'metric' => [
        'orders' => 'Pasūtījumi',
        'turnover' => 'Apgrozījums (ar PVN)',
        'profit' => 'Peļņa (aptuveni, bez PVN)',
        'profit_vs' => 'Peļņa pret :name',
    ],
    'bucket' => [
        'unprocessed' => 'Neapstrādāti',
        'processing' => 'Apstrādē',
        'shipped' => 'Nosūtīti',
        'canceled' => 'Atcelti',
    ],
    'bucket_dimension' => [
        'unprocessed' => 'Neapstrādātie pasūtījumi',
        'processing' => 'Pasūtījumi apstrādē',
        'shipped' => 'Nosūtītie pasūtījumi',
        'canceled' => 'Atceltie pasūtījumi',
    ],
    'widget' => [
        'new_orders' => 'Shopaholic: Jaunie pasūtījumi',
        'new_orders_title' => 'Jaunie pasūtījumi',
        'loading' => 'Ielādē...',
        'no_orders' => 'Nav neapstrādātu pasūtījumu',
        'view_orders' => 'Skatīt pasūtījumus',
        'chart_title' => 'Apgrozījums un peļņa',
        'by_status' => 'Pasūtījumi pēc statusa',
        'by_payment' => 'Pasūtījumi pēc apmaksas veida',
    ],
];
