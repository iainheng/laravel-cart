<?php

namespace Nextbyte\Cart\Contracts;

interface Buyable
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null);

    /**
     * Get the title of the Buyable item.
     *
     * @return string
     */
    public function getBuyableName($options = null);

    /**
     * Get the description of the Buyable item.
     *
     * @return string
     */
    public function getBuyableDescription($options = null);

    /**
     * Get the price of the Buyable item.
     *
     * @return float
     */
    public function getBuyablePrice($options = null);

    /**
     * Get is discountable of the Buyable item.
     *
     * @return float
     */
    public function getBuyableDiscountable($options = null);
}