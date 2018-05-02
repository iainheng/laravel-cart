<?php

namespace Nextbyte\Cart;

use Illuminate\Contracts\Support\Arrayable;
use Nextbyte\Cart\Contracts\Buyable;
use Illuminate\Contracts\Support\Jsonable;

class CartDetail extends CartItem implements Arrayable, Jsonable
{
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        throw new \InvalidArgumentException('Cart detail cannot create from buyable', 500);
    }
}
