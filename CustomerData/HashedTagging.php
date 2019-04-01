<?php
/**
 * Created by PhpStorm.
 * User: mridang
 * Date: 12/04/2019
 * Time: 15.38
 */

namespace Nosto\Tagging\CustomerData;

abstract class HashedTagging
{

    /**
     * @var string the algorithm to use for hashing visitor id.
     */
    const VISITOR_HASH_ALGO = 'sha256';

    /**
     * Return the checksum for for the customer tagging i.e hashed cookie identifier or HCID for
     * short. This is used to sign the tagging so that if it is in fact cached, the cookie and
     * tagging signature won't match and the data will be discarded
     *
     * @param string $string
     * @return string
     */
    public static function generateVisitorChecksum($string)
    {
        return hash(self::VISITOR_HASH_ALGO, $string);
    }
}
