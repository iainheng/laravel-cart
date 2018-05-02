<?php

namespace Nextbyte\Cart;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use Nextbyte\Cart\Contracts\Buyable;
use Nextbyte\Cart\Exceptions\UnknownModelException;
use Nextbyte\Cart\Exceptions\InvalidRowIDException;
use Nextbyte\Cart\Exceptions\CartAlreadyStoredException;

class Cart implements \Nextbyte\Cart\Contracts\Cart
{
    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     *
     * @var \Illuminate\Contracts\Session\Session
     */
    private $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Cart constructor.
     *
     * @param \Illuminate\Contracts\Session\Session      $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(Session $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Magic method to make accessing properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'total') {
            return $this->total();
        }

        if($attribute === 'tax') {
            return $this->tax();
        }

        if($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     * @return \Nextbyte\Cart\Cart
     */
    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance()
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Get all items in cart
     *
     * @return Collection
     */
    public function items()
    {
        return $this->getContent()->get('items', new Collection);
    }

    /**
     * Get all order details in cart
     *
     * @return Collection
     */
    public function details()
    {
        return $this->getContent()->get('details', new Collection());
    }

    /**
     * Get all additional attributes in cart
     * @return Collection
     */
    public function attributes()
    {
        return $this->getContent()->get('attributes', new Collection);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $quantity
     * @param float     $price
     * @param array     $options
     * @return \Nextbyte\Cart\CartItem
     */
    public function addItem($id, $name = null, $description = null, $quantity = null, $price = null, $discountable = true, array $options = [])
    {
        $items = $this->items();

        $cartItem = $this->createCartItem($id, $name, $description, $quantity, $price, $discountable, $options);

        if ($items->has($cartItem->rowId)) {
            $cartItem->quantity += $items->get($cartItem->rowId)->quantity;
        }

        $items->put($cartItem->rowId, $cartItem);

        $this->events->fire('cart.item_added', $cartItem);

        $content = $this->getContent()->put('items', $items);

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $quantity
     * @return \Nextbyte\Cart\CartItem
     */
    public function updateItem($rowId, $quantity)
    {
        $cartItem = $this->getItem($rowId);

        if ($quantity instanceof Buyable) {
            $cartItem->updateFromBuyable($quantity);
        } elseif (is_array($quantity)) {
            $cartItem->updateFromArray($quantity);
        } else {
            $cartItem->quantity = $quantity;
        }

        $items = $this->items();

        if ($rowId !== $cartItem->rowId) {
            $items->pull($rowId);

            if ($items->has($cartItem->rowId)) {
                $existingCartItem = $this->getItem($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->quantity + $cartItem->quantity);
            }
        }

        if ($cartItem->quantity <= 0) {
            $this->removeItem($cartItem->rowId);
            return;
        } else {
            $items->put($cartItem->rowId, $cartItem);
        }

        $this->events->fire('cart.updated', $cartItem);

        $content = $this->getContent();
        $content->put('items', $items);

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function removeItem($rowId)
    {
        $cartItem = $this->getItem($rowId);

        $items = $this->items();

        $items->pull($cartItem->rowId);

        $this->events->fire('cart.item_removed', $cartItem);

        $content = $this->getContent();
        $content->put('items', $items);

        $this->session->put($this->instance, $content);
    }

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
    public function addDetail($type, $name = null, $description = null, $quantity = null, $price = null, $discountable = false, array $options = [])
    {
        $details = $this->details();

        $cartDetail = $this->createCartDetail([
            'name' => $name,
            'description' => $description,
            'quantity' => $quantity,
            'price' => $price,
            'type' => $type,
            'discountable' => $discountable,
            'options' => $options
        ]);

        if ($details->has($cartDetail->rowId)) {
            $cartDetail->quantity += $details->get($cartDetail->rowId)->quantity;
        }

        $details->put($cartDetail->rowId, $cartDetail);

        $this->events->fire('cart.detail_added', $cartDetail);

        $content = $this->getContent()->put('details', $details);

        $this->session->put($this->instance, $content);

        return $cartDetail;
    }

    /**
     * Remove the cart detail with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function removeDetail($rowId)
    {
        $cartDetail = $this->getDetail($rowId);

        $details = $this->details();

        $details->pull($cartDetail->rowId);

        $this->events->fire('cart.detail_removed', $cartDetail);

        $content = $this->getContent();
        $content->put('details', $details);

        $this->session->put($this->instance, $content);
    }

    /**
     * Add an attribute to cart
     *
     * @param string    $key
     * @param mixed     $value
     * @param array     $options
     * @return Collection
     */
    public function addAttribute($key, $value = null, array $options = [])
    {
        $attributes = $this->attributes();

        $attributes->put($key, $value);

        $this->events->fire('cart.attribute_added', $key, $value);

        $content = $this->getContent()->put('attributes', $attributes);

        $this->session->put($this->instance, $content);

        return $attributes;
    }

    /**
     * Remove the cart attribute with the given key from the cart.
     *
     * @param string $key
     * @return void
     */
    public function removeAttribute($key)
    {
        $attribute = $this->getAttribute($key);

        $attributes = $this->attributes();

        $attributes->pull($key);

        $this->events->fire('cart.attribute_removed', $attribute);

        $content = $this->getContent();
        $content->put('attributes', $attributes);

        $this->session->put($this->instance, $content);
    }

    /**
     * Get single cart item by its key
     *
     * @param string $key
     * @param string $field
     * @param mixed $default
     * @return CartItem|null
     */
    public function getItem($key, $field = null, $default = null)
    {
        $items = $this->items();

        if (!$field)
            return ($items->has($key)) ? $items->get($key) : $default;

        $item = $items->first(function ($item, $key) use ($field) {
            return $item->$field == $key;
        });


        return $item;
    }

    /**
     * Get single cart detail by its key
     *
     * @param string $key
     * @param string $field
     * @param mixed $default
     * @return CartDetail|null
     */
    public function getDetail($key, $field = null, $default = null)
    {
        $details = $this->details();

        if (!$field)
            return ($details->has($key)) ? $details->get($key) : $default;

        $item = $details->first(function ($item, $key) use ($field) {
            return $item->$field == $key;
        });


        return $item;
    }

    /**
     * Get a cart attribute from the cart by its key.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        $attributes = $this->attributes();

        if (!$attributes->has($key))
            return $default;

        return $attributes->get($key);
    }

    /**
     * Remove all items from cart
     *
     * @return void
     */
    public function clearItems()
    {
        $content = $this->getContent();

        $content->forget('items');

        $this->session->put($this->instance, $content);
    }

    /**
     * Remove all details from cart
     *
     * @return void
     */
    public function clearDetails()
    {
        $content = $this->getContent();

        $content->forget('details');

        $this->session->put($this->instance, $content);
    }

    /**
     * Remove all attributes from cart
     *
     * @return void
     */
    public function clearAttributes()
    {
        $content = $this->getContent();

        $content->forget('attributes');

        $this->session->put($this->instance, $content);
    }

    /**
     * Reset cart and remove everything
     *
     * @return void
     */
    public function clear()
    {
        $this->clearItems();
        $this->clearDetails();
        $this->clearAttributes();
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        $items = $this->items();

        return $items->sum('quantity');
    }

    /**
     * Check if current cart is empty
     *
     * @return bool
     */
    public function empty()
    {
        return $this->count() == 0;
    }

    /**
     * Get total items amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function itemsTotal($withTax = false)
    {
        $items = $this->items();

        $total = $items->reduce(function ($total, CartItem $cartItem) use ($withTax) {
            return $total + ($withTax ? $cartItem->total() : $cartItem->subtotal());
        }, 0);

        return $total;
    }

    /**
     * Get total taxed amount of all items
     *
     * @return float
     */
    public function itemsTaxedTotal()
    {
        $items = $this->items();

        $total = $items->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->taxed();
        }, 0);

        return $total;
    }

    /**
     * Get total details amount optionally with tax
     *
     * @param bool $withTax
     * @return float
     */
    public function detailsTotal($withTax = false)
    {
        $details = $this->details();

        $total = $details->reduce(function ($total, CartDetail $cartDetail) use ($withTax) {
            return $total + ($withTax ? $cartDetail->total() : $cartDetail->subtotal());
        }, 0);

        return $total;
    }

    /**
     * Get total taxed amount of all details
     *
     * @return float
     */
    public function detailsTaxedTotal()
    {
        $details = $this->details();

        $total = $details->reduce(function ($total, CartDetail $cartDetail) {
            return $total + $cartDetail->taxed();
        }, 0);

        return $total;
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return float
     */
    public function subtotal()
    {
        return $this->itemsTotal();
    }

    /**
     * Get final total of whole cart
     *
     * @return float
     */
    public function total()
    {
        return $this->itemsTotal(true) + $this->detailsTotal(true);
    }

    /**
     * Search the cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function searchItems(Closure $search)
    {
        return $this->items()->filter($search);
    }

    /**
     * Search the cart details matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function searchDetails(Closure $search)
    {
        return $this->details()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     * @return void
     */
    public function associate($rowId, $model)
    {
        if(is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->getItem($rowId);

        $cartItem->associate($model);

        $items = $this->items();

        $items->put($cartItem->rowId, $cartItem);

        $content = $this->getContent();

        $content->put('items', $items);

        $this->session->put($this->instance, $content);
    }

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();

        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->currentInstance(),
            'content' => serialize($content)
        ]);

        $this->events->fire('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore($identifier)
    {
        if( ! $this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize($stored->content);

        $currentInstance = $this->currentInstance();

        $this->instance($stored->instance);

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->fire('cart.restored');

        $this->session->put($this->instance, $content);

        $this->instance($currentInstance);

        $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->delete();
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection
     *
     * @return \Illuminate\Support\Collection
     */
    public function getContent()
    {
        $content = $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection;

        return $content;
    }


    public function createCartItem($id, $name, $description, $quantity, $price = null, $discountable = true, array $options)
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id, $quantity ?: []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['quantity']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $description, $price, null, $discountable, $options);
            $cartItem->setQuantity($quantity);
        }

        $cartItem->setTaxRate(config('cart.tax'));

        return $cartItem;
    }

    /**
     * @param $fields array
     */
    public function createCartDetail($fields)
    {
        $cartDetail = CartItem::fromArray($fields);
        $cartDetail->setQuantity($fields['quantity']);

        $cartDetail->setTaxRate(0.6);

        return $cartDetail;
    }

    /**
     * @param $identifier
     * @return bool
     */
    public function storedCartWithIdentifierExists($identifier)
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->exists();
    }

    /**
     * Get the database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    private function getConnection()
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName()
    {
        return config('cart.database.table', 'shoppingcart');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    public function getConnectionName()
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }
}
