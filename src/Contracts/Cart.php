<?php
/**
 * Cart.php
 *
 * @author    Iain Heng <hengcs@gmail.com>
 * @copyright 2018 Heng Cheng Siang
 * @link      https://github.com/hengcs
 */

namespace Nextbyte\Cart\Contracts;

use Closure;
use Illuminate\Support\Collection;
use Nextbyte\Cart\CartDetail;
use Nextbyte\Cart\CartItem;

interface Cart
{
    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     * @return \Nextbyte\Cart\Cart
     */
    public function instance($instance = null);

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance();

    /**
     * Get all items in cart
     *
     * @return Collection
     */
    public function items();

    /**
     * Get all order details in cart
     *
     * @return Collection
     */
    public function details();

    /**
     * Get all additional attributes in cart
     * @return Collection
     */
    public function attributes();

    /**
     * Add an item to the cart.
     *
     * @param mixed $id
     * @param mixed $name
     * @param int|float $quantity
     * @param float $price
     * @param array $options
     * @return \Nextbyte\Cart\CartItem
     */
    public function addItem($id, $name = null, $description = null, $quantity = null, $price = null, $discountable = true, array $options = []);

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed $quantity
     * @return \Nextbyte\Cart\CartItem
     */
    public function updateItem($rowId, $quantity);

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function removeItem($rowId);

    /**
     * @param $type
     * @param mixed $name
     * @param string|null $description
     * @param float|integer|null $quantity
     * @param float|null $price
     * @param bool $discountable
     * @param array $options
     * @return CartItem
     */
    public function addDetail($type, $name = null, $description = null, $quantity = null, $price = null, $discountable = false, array $options = []);

    /**
     * Remove the cart detail with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function removeDetail($rowId);

    /**
     * Add an attribute to cart
     *
     * @param string $key
     * @param mixed $value
     * @param array $options
     * @return Collection
     */
    public function addAttribute($key, $value = null, array $options = []);

    /**
     * Remove the cart attribute with the given key from the cart.
     *
     * @param string $key
     * @return void
     */
    public function removeAttribute($key);

    /**
     * Get single cart item by its key
     *
     * @param string $key
     * @param string $field
     * @param mixed $default
     * @return CartItem|null
     */
    public function getItem($key, $field = null, $default = null);

    /**
     * Get single cart detail by its key
     *
     * @param string $key
     * @param string $field
     * @param mixed $default
     * @return CartDetail|null
     */
    public function getDetail($key, $field = null, $default = null);

    /**
     * Get a cart attribute from the cart by its key.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key, $default = null);

    /**
     * Remove all items from cart
     *
     * @return void
     */
    public function clearItems();

    /**
     * Remove all details from cart
     *
     * @return void
     */
    public function clearDetails();

    /**
     * Remove all attributes from cart
     *
     * @return void
     */
    public function clearAttributes();

    /**
     * Reset cart and remove everything
     *
     * @return void
     */
    public function clear();

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy();

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content();

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count();

    /**
     * Get total items amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function itemsTotal($withTax = false);

    /**
     * Get total taxed amount of all items
     *
     * @return float
     */
    public function itemsTaxedTotal();

    /**
     * Get total details amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function detailsTotal($withTax = false);

    /**
     * Get total taxed amount of all details
     *
     * @return float
     */
    public function detailsTaxedTotal();

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function subtotal();

    /**
     * Get final total of whole cart
     *
     * @return float
     */
    public function total();

    /**
     * Search the cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function searchItems(Closure $search);

    /**
     * Search the cart details matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function searchDetails(Closure $search);

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed $model
     * @return void
     */
    public function associate($rowId, $model);

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     */
    public function store($identifier);

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore($identifier);

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection
     *
     * @return \Illuminate\Support\Collection
     */
    public function getContent();

    public function createCartItem($id, $name, $description, $quantity, $price = null, $discountable = true, array $options);

    /**
     * @param $fields array
     */
    public function createCartDetail($fields);

    /**
     * @param $identifier
     * @return bool
     */
    public function storedCartWithIdentifierExists($identifier);

    /**
     * Check if current cart is empty
     *
     * @return bool
     */
    public function empty();
}