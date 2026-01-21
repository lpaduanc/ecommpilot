<?php

namespace App\Enums;

enum Platform: string
{
    case Nuvemshop = 'nuvemshop';
    case Shopify = 'shopify';
    case WooCommerce = 'woocommerce';
    case Vtex = 'vtex';
    case Tray = 'tray';

    public function label(): string
    {
        return match ($this) {
            self::Nuvemshop => 'Nuvemshop',
            self::Shopify => 'Shopify',
            self::WooCommerce => 'WooCommerce',
            self::Vtex => 'VTEX',
            self::Tray => 'Tray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
