<?php declare(strict_types=1);

namespace GXModules\Library\Core\Settings\Options;

class Integration {

//  Possible values of this enum, we use payment link only
    public const PAYMENT_LINK           = 'payment_link';
    public const PAYMENT_PAGE           = 'payment_page';
    public const CHARGE_FLOW            = 'charge_flow';
    public const DIRECT_CARD_PROCESSING = 'direct_card_processing';
    public const IFRAME                 = 'iframe';
    public const LIGHTBOX               = 'lightbox';
    public const MOBILE_WEB             = 'mobile_web_view';
    public const TERMINAL               = 'terminal';

//  Gets allowable values of the enum
    public static function getAllowableEnumValues(): array {
        return [
            self::PAYMENT_LINK,
            self::CHARGE_FLOW,
            self::DIRECT_CARD_PROCESSING,
            self::IFRAME,
            self::LIGHTBOX,
            self::MOBILE_WEB,
            self::PAYMENT_PAGE,
            self::TERMINAL,
	];
    }
}