<?php

add_action('phpmailer_init', function ($mailer) {
    $mailer->isSMTP();
    $mailer->Host = 'mailpit';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
});

add_action('woocommerce_init', function () {
    if (! function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    woocommerce_register_additional_checkout_field([
        'id' => 'arctron/cpf',
        'label' => 'CPF (11 dígitos)',
        'location' => 'order',
        'required' => true,
        'type' => 'text',
    ]);
});

add_action('woocommerce_validate_additional_field', function ($errors, $key, $value) {
    if ($key === 'arctron/cpf' && ! preg_match('/^\d{11}$/', preg_replace('/[.\-\s]/', '', (string) $value))) {
        $errors->add('invalid_cpf', 'Informe o CPF com 11 dígitos.');
    }
}, 10, 3);

add_action('woocommerce_set_additional_field_value', function ($key, $value, $group, $object) {
    if ($key === 'arctron/cpf' && $group === 'other' && $object instanceof WC_Order) {
        $object->update_meta_data('_billing_cpf', preg_replace('/[.\-\s]/', '', (string) $value));
    }
}, 10, 4);
