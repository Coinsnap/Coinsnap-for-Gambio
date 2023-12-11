<?php
namespace Coinsnap\CoinsnapPayment\Components\Utility;

class UrlHelper {
    public static function getModuleJsFile($filename): string {
        return static::getModulePath() . "Javascripts/$filename";
    }

    public static function getModulePath(): string {
        return '/../../../../GXModules/Coinsnap/CoinsnapPayment/';
    }
}
