<?php return [
    'plugin' => [
        'name' => 'Shopaholic-dashbord',
        'description' => 'Butikkdashbord: ordregrupper, omsetning, fortjenestemetrikker, widget for nye ordrer',
    ],
    'settings' => [
        'label' => 'Dashbord',
        'description' => 'Kostpristype for fortjenestemetrikken',
        'cost_price_type' => 'Kostpristype',
        'cost_price_type_comment' => 'Pristypen med innkjøpsprisene (kost), f.eks. Vairum. Fortjeneste = ordreinntekt ekskl. MVA minus denne prisen per posisjon. Tom = ingen fortjenestemetrikk på dashbordet. Om pristypen lagrer priser med eller uten MVA angis på selve pristypen (Shopaholic > Pristyper).',
        'cost_price_type_empty' => '-- ingen, fortjenestemetrikk deaktivert --',
    ],
    'permission' => [
        'tab' => 'Shopaholic-dashbord',
        'access_dashboard' => 'Tilgang til butikkdashbordet',
        'view_profit' => 'Se fortjenestemetrikker',
    ],
    'field' => [
        'price_includes_vat' => 'Prisene inkluderer MVA (brutto)',
        'price_includes_vat_comment' => 'Av = priser lagret uten MVA (netto). Brukes av fortjenestemetrikken når denne pristypen er kostgrunnlaget',
        'price_includes_vat_column' => 'Inkl. MVA',
    ],
    'dimension' => [
        'date' => 'Dato',
        'status' => 'Ordrestatus',
        'payment_method' => 'Betalingsmåte',
    ],
    'metric' => [
        'orders' => 'Ordrer',
        'turnover' => 'Omsetning (inkl. MVA)',
        'profit' => 'Fortjeneste (ca., ekskl. MVA)',
        'profit_vs' => 'Fortjeneste mot :name',
    ],
    'bucket' => [
        'unprocessed' => 'Ubehandlede',
        'processing' => 'Under behandling',
        'shipped' => 'Sendt',
        'canceled' => 'Kansellert',
    ],
    'bucket_dimension' => [
        'unprocessed' => 'Ubehandlede ordrer',
        'processing' => 'Ordrer under behandling',
        'shipped' => 'Sendte ordrer',
        'canceled' => 'Kansellerte ordrer',
    ],
    'widget' => [
        'new_orders' => 'Shopaholic: Nye ordrer',
        'new_orders_title' => 'Nye ordrer',
        'loading' => 'Laster...',
        'no_orders' => 'Ingen ubehandlede ordrer',
        'view_orders' => 'Vis ordrer',
        'chart_title' => 'Omsetning og fortjeneste',
        'by_status' => 'Ordrer etter status',
        'by_payment' => 'Ordrer etter betalingsmåte',
    ],
];
