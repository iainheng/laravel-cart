<?php

namespace Nextbyte\Cart;

use Illuminate\Contracts\Support\Arrayable;
use Nextbyte\Cart\Contracts\Buyable;
use Illuminate\Contracts\Support\Jsonable;

class CartItem implements Arrayable, Jsonable
{
    const TYPE_SUBTOTAL = 'subtotal';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_SHIPPING = 'shipping';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_ADMIN_FEES = 'adminfees';
    const TYPE_ITEM = 'item';

    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * @var string
     */
    public $type;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $quantity;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The description of the cart item.
     *
     * @var string
     */
    public $description;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public $price;

    /**
     * @var boolean
     */
    public $isTax;

    /**
     * The tax rate for the cart item.
     *
     * @var int|float
     */
    public $taxRate;

    /**
     * @var boolean
     */
    public $taxIncluded;

    /**
     * @var boolean
     */
    public $discountable;

    /**
     * The options for this cart item.
     *
     * @var array
     */
    public $options;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private $associatedModel = null;


    public function __construct($id, $name, $description = null, $price = null, $type = null, $discountable = true, array $options = [])
    {
        if(empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if(empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if(strlen($price) < 0 || ! is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }

        $this->id       = $id;
        $this->name     = $name;
        $this->description = $description;
        $this->price    = floatval($price);
        $this->options  = new CartItemOptions($options);
        $this->type     = ($type) ?: self::TYPE_ITEM;
        $this->discountable = $discountable;
        $this->rowId = $this->generateRowId($id, $options);
    }

    public function subtotal()
    {
        return $this->quantity * $this->price;
    }
    
    public function total()
    {
        return ($this->isTax && !$this->taxIncluded) ? $this->subtotal() + $this->taxed() : $this->subtotal();
    }

    public function taxed()
    {
        return ($this->isTax) ? tax_amount($this->subtotal(), $this->taxRate * 100, $this->taxIncluded) : 0;
    }

    public function taxable()
    {
        return ($this->isTax) ? (($this->taxIncluded) ? $this->total() - $this->taxed() : $this->subtotal()) : 0;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param \Nextbyte\Cart\Contracts\Buyable $item
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id       = $item->getBuyableIdentifier($this->options);
        $this->name     = $item->getBuyableName($this->options);
        $this->description     = $item->getBuyableDescription($this->options);
        $this->price    = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id       = array_get($attributes, 'id', $this->id);
        $this->quantity = array_get($attributes, 'quantity', $this->quantity);
        $this->description = array_get($attributes, 'description', $this->description);
        $this->name     = array_get($attributes, 'name', $this->name);
        $this->price    = array_get($attributes, 'price', $this->price);
        $this->options  = new CartItemOptions(array_get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     * @return \Nextbyte\Cart\CartItem
     */
    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);
        
        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int|float $taxRate
     * @param boolean $taxIncluded
     * @return \Nextbyte\Cart\CartItem
     */
    public function setTaxRate($taxRate, $taxIncluded = false)
    {
        $this->taxRate = $taxRate;
        $this->taxIncluded = $taxIncluded;
        
        return $this;
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if(empty($qty) || ! is_numeric($qty))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->quantity = $qty;
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \Nextbyte\Cart\Contracts\Buyable $item
     * @param array                                      $options
     * @return \Nextbyte\Cart\CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        return new self(
            $item->getBuyableIdentifier($options),
            $item->getBuyableName($options),
            $item->getBuyableDescription($options),
            $item->getBuyablePrice($options),
            null,
            $item->getBuyableDiscountable(),
            $options
        );
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \Nextbyte\Cart\CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options = array_get($attributes, 'options', []);

        return new self(
            $attributes['id'],
            $attributes['name'],
            $attributes['description'],
            $attributes['price'],
            $attributes['type'],
            $attributes['discountable'],
            $options
        );
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     * @return \Nextbyte\Cart\CartItem
     */
    public static function fromAttributes($id, $name, $description = null, $price = null, $type = null, $discountable = true, array $options = [])
    {
        return new self($id, $name, $description, $price, $type, $discountable, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'price'    => $this->price,
            'tax_rate' => $this->taxRate,
            'tax_included' => $this->taxIncluded,
            'options'  => $this->options->toArray(),
            'taxed'    => $this->taxed(),
            'taxable'  => $this->taxable(),
            'subtotal' => $this->subtotal(),
            'total'    => $this->total()
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
}
