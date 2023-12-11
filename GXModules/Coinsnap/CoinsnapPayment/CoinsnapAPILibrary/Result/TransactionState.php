<?php
declare(strict_types=1);
namespace Coinsnap\Result;

class TransactionState {
    
    const NEW = 'New';
    const EXPIRED = 'Expired';
    const SETTLED = 'Settled';
    const PROCESSING = 'PROCESSING';
    
    //  Gets allowable values of the enum
    public static function getAllowableEnumValues(){// @return string[]
        return [
            self::NEW,
            self::EXPIRED,
            self::SETTLED,
            self::PROCESSING
        ];
    }
}


