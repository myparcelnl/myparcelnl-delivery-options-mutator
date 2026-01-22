<?php
/*
 * Plugin Name: MyParcelNL delivery options mutator
 * Plugin URI:
 * Description: Starting point to automatically modify MyParcelNL delivery options on WooCommerce orders.
 * Author: MyParcel
 * Author URI: https://www.myparcel.nl
 * Version: 0.0.2
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Requires Plugins: woocommerce, woocommerce-myparcel
 */

declare(strict_types=1);

// 11 is after MyParcelNL plugin saves the delivery options (priority 10)
add_action('woocommerce_blocks_checkout_order_processed', 'myparcelnl_do_mutator', 11, 1);
add_action('woocommerce_checkout_order_processed', 'myparcelnl_do_mutator_classic', 11, 3);

function myparcelnl_do_mutator_classic($a, $b, WC_Order $order): void
{
    myparcelnl_do_mutator($order);
}

function myparcelnl_do_mutator_status_changed($order_id): void
{
    $order = wc_get_order($order_id);
    myparcelnl_do_mutator($order);
}

function myparcelnl_do_mutator(WC_Order $order): void
{
    $alreadyRan = $order->get_meta('_myparcelnl_delivery_options_mutator_done');
    if ($alreadyRan) {
        return;
    }

    $options = $order->get_meta('_myparcelnl_order_data');

    //file_put_contents(__DIR__ . '/debug.log', var_export($options, true) . " <- before\n", FILE_APPEND);

    if (! is_array($options)) {
        $options = array();
    }

    /**
     * Ensure the expected structure is present in the options array.
     */
    if (! isset($options['deliveryOptions'])) {
        $options['deliveryOptions'] = array();
    }

    if (! isset($options['deliveryOptions']['carrier']['externalIdentifier'])) {
        $options['deliveryOptions']['carrier'] = array('externalIdentifier' => 'dhlforyou');
    }

    /**
     * Logic to modify the delivery options as needed.
     * In this case, we don’t want to modify options if dhlforyou was not chosen or set by default.
     */
    if ('dhlforyou' !== $options['deliveryOptions']['carrier']['externalIdentifier']) {
        return;
    }

    $totalWeight = 0;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if (! $product || $product->is_virtual()) {
            return;
        }

        $amount = (int) ($item['qty'] ?? 1);
        $weight = myparcelnl_convert_weight_to_grams($product->get_weight());

        $totalWeight += ($weight * $amount);
    }

    if (8100 < $totalWeight) {
        $options['deliveryOptions']['labelAmount'] = 2;
    } else {
        $options['deliveryOptions']['labelAmount'] = 1;
    }

    //file_put_contents(__DIR__ . '/debug.log', var_export($options, true) . " <- after\n", FILE_APPEND);

    /**
     * Set the already done flag and save the modified options back to the order.
     */
    $order->update_meta_data('_myparcelnl_delivery_options_mutator_done', true);
    $order->update_meta_data('_myparcelnl_order_data', $options);
    $order->save_meta_data();
}

function myparcelnl_convert_weight_to_grams($weight): int
{
    $weightUnit  = get_option('woocommerce_weight_unit');
    $floatWeight = (float) $weight;

    switch ($weightUnit) {
        case 'kg':
            $weight = $floatWeight * 1000;
            break;
        case 'lbs':
            $weight = $floatWeight / 0.45359237;
            break;
        case 'oz':
            $weight = $floatWeight / 0.0283495231;
            break;
        default:
            $weight = $floatWeight;
            break;
    }

    return (int) ceil($weight);
}


