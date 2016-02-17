<?php

/**
 *  preferredshipping.php
 *
 * @package
 * @copyright Copyright 2003-2016 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version Author: bislewl  12/13/2016 1:32 PM Modified in zen_preferred_shipping
 */

class preferredshipping extends base
{
    var $code, $title, $description, $icon, $enabled;

    function __construct()
    {
        global $order, $db;

        $this->code = 'preferredshipping';
        $this->title = MODULE_SHIPPING_PREFERRED_SHIPPING_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_PREFERRED_SHIPPING_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SHIPPING_PREFERRED_SHIPPING_SORT_ORDER;
        $this->icon = MODULE_SHIPPING_PREFERRED_SHIPPING_ICON;
        $this->tax_class = MODULE_SHIPPING_PREFERRED_SHIPPING_TAX_CLASS;
        $this->tax_basis = MODULE_SHIPPING_PREFERRED_SHIPPING_TAX_BASIS;

        if (zen_get_shipping_enabled($this->code)) {
            $this->enabled = ((MODULE_SHIPPING_PREFERRED_SHIPPING_STATUS == 'True') ? true : false);
        }

        if (($this->enabled == true) && ((int)MODULE_SHIPPING_PREFERRED_SHIPPING_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_PREFERRED_SHIPPING_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }


    function quote($method = '')
    {
        global $order;
        if ($this->enabled) {

            $excluded_array = explode(',', MODULE_SHIPPING_PREFERRED_SHIPPING_EXCLUDED_PRODUCTS);
            $products_in_cart_array = explode(',',$_SESSION['cart']->get_product_id_list());
            echo var_dump($products_in_cart_array);
            foreach ($products_in_cart_array as $product_in_cart) {
                $base_product_in_cart = substr($product_in_cart, 0, strpos($product_in_cart, ':'));
                if (in_array($base_product_in_cart, $excluded_array)) {
                    $this->enabled = false;
                }
            }

            if ($_SESSION['cart']->show_total() < MODULE_SHIPPING_PREFERRED_SHIPPING_MIN_ORDER) {
                $this->enabled = false;
            }
        }

        if ($this->enabled) {
            $this->quotes = array('id' => $this->code,
                'module' => MODULE_SHIPPING_PREFERRED_SHIPPING_TEXT_TITLE,
                'methods' => array(array('id' => $this->code,
                    'title' => MODULE_SHIPPING_PREFERRED_SHIPPING_TEXT_WAY,
                    'cost' => '0.00')));

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }

            if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }

        return $this->quotes;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_PREFERRED_SHIPPING_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function install()
    {
        $config_values = $this->configuration_values();
        foreach ($config_values as $config_value) {
            $config_value['configuration_group_id'] = '6';
            $config_value['date_added'] = 'now()';
            zen_db_perform(TABLE_CONFIGURATION, $config_value);
        }
    }

    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE  'MODULE\_SHIPPING\_PREFERRED_SHIPPING\_%'");
    }

    function keys()
    {
        $keys = array();
        $config_values = $this->configuration_values();
        foreach ($config_values as $config_value) {
            $keys[] = $config_value['configuration_key'];
        }
        return $keys;
    }

    function configuration_values()
    {
        $config_values = array();
        $config_values[] = array(
            'configuration_title' => 'Enable Preferred Shipping',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_STATUS',
            'configuration_value' => 'True',
            'configuration_description' => 'Preferred Shipping is used for a custom free shipping solution',
            'sort_order' => '0',
            'set_function' => 'zen_cfg_select_option(array(\'True\', \'False\'),'
        );
        $config_values[] = array(
            'configuration_title' => 'Tax Class',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_TAX_CLASS',
            'configuration_value' => '0',
            'configuration_description' => 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone',
            'sort_order' => '1',
            'use_function' => 'zen_get_tax_class_title',
            'set_function' => 'zen_cfg_pull_down_tax_classes('
        );
        $config_values[] = array(
            'configuration_title' => 'Tax Basis',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_TAX_BASIS',
            'configuration_value' => 'Shipping',
            'configuration_description' => 'Use the following tax class on the shipping fee.',
            'sort_order' => '2',
            'set_function' => 'zen_cfg_select_option(array(\'Shipping\', \'Billing\', \'Store\'), '
        );
        $config_values[] = array(
            'configuration_title' => 'Shipping Zone',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_ZONE',
            'configuration_value' => '0',
            'configuration_description' => 'If a zone is selected, only enable this shipping method for that zone.',
            'sort_order' => '3',
            'use_function' => 'zen_get_zone_class_title',
            'set_function' => 'zen_cfg_pull_down_zone_classes('
        );
        $config_values[] = array(
            'configuration_title' => 'Minimum Order',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_MIN_ORDER',
            'configuration_value' => '1000.00',
            'configuration_description' => 'Minimum Order to qulaify for free shipping',
            'sort_order' => '10',
        );

        $config_values[] = array(
            'configuration_title' => 'Excluded Products',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_EXCLUDED_PRODUCTS',
            'configuration_value' => '',
            'configuration_description' => 'Products Excluded from free shipping',
            'sort_order' => '20',
        );
        $config_values[] = array(
            'configuration_title' => 'Sort Order',
            'configuration_key' => 'MODULE_SHIPPING_PREFERRED_SHIPPING_SORT_ORDER',
            'configuration_value' => '0',
            'configuration_description' => 'Sort order of display.',
            'sort_order' => '99',
        );

        return $config_values;
    }
}