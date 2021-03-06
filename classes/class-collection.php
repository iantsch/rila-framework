<?php
namespace Rila;

/**
 * Handles collections of the "Item" class.
 */
class Collection implements \Iterator, \Countable  {
	/**
	 * Holds the item type, which can be handled by the class.
	 *
	 * @since 0.1
	 * @var string
	 */
	protected $item_type = 'Rila\\Item';

	/**
	 * Holds the items, which will be used within the collection.
	 *
	 * @since 0.1
	 * @var Item[]
	 */
	protected $items = array();

	/**
	 * Holds arguments, which will be used whel loading items from the DB.
	 *
	 * @since 0.1
	 * @var mixed[]
	 */
	public $args;

	/**
	 * Sometimes instead of full arguments, only IDs could be passed. Check this one first.
	 *
	 * @since 0.1
	 * @var int[]
	 */
	protected $ids;

	/**
	 * Indicates if the collection is already initialized.
	 *
	 * @since 0.1
	 * @var bool
	 */
	protected $initialized = false;

	/**
	 * Holds the pointer to the current item.
	 *
	 * @since 0.1
	 * @var int
	 */
	protected $pointer = 0;

	/**
	 * Initializes a collection.
	 *
	 * @since 0.1
	 * @param mixed[] $request Either an array of Item or data about querying them.
	 */
	public function __construct( $request = null ) {
		if( is_null( $request ) ) {
			# Allow simple initialization of child classes
			if( method_exists( $this, 'initialize' ) ) {
				$this->initialize();
			}

			return;
		}

		if( is_array( $request ) ) {
			$items_only = true;
			$ids_only   = true;
			$ids        = array();
			$k          = 0;

			foreach( $request as $i => $item ) {
				if( ! is_object( $item ) ) {
					$items_only = false;
				}

				if( ( $i !== $k ) || ! is_scalar( $item ) || ! intval( $item ) ) {
					$ids_only = false;
				} else {
					$ids[] = intval( $item );
				}

				$k++;
			}

			if( $ids_only ) {
				$this->ids = $ids;
			} elseif( $items_only ) {
				$this->items = array();
				foreach( $request as $item ) {
					$this->items[] = call_user_func( array( $this->item_type, 'factory' ),  $item );
				}
				$this->initialized = true;
			} else {
				$this->args = $request;
			}
		} else {
			$this->args = wp_parse_args( $request );
		}

		# Allow simple initialization of child classes
		if( method_exists( $this, 'initialize' ) ) {
			$this->initialize();
		}
	}

	/**
	 * Loads items from the database.
	 *
	 * In this class, this method is unaware of item types and cannot retrieve
	 * anything from the database, so it should be overloaded for child classes,
	 * meant for handling specific types of item.
	 *
	 * The method must use the "ids" and "args" properties in that order.
	 *
	 * @since 0.1
	 */
	protected function load() {
		$msg = "Generic collections cannot load from the database and can only be used with explicit data.";
		throw new \Exception( $msg );
	}

	/**
	 * Checks if the collection is already initialized and attepmpts loading.
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	protected function check() {
		if( $this->initialized ) {
			return true;
		}

		$this->load();

		return $this->initialized;
	}

	/**
	 *
	 * @since 0.1
	 * @return mixed
	 */
	public function current() {
		if( ! $this->check() ) {
			return false;
		}

		return $this->items[ $this->pointer ];
	}

	/**
	 *
	 * @since 0.1
	 * @return scalar
	 */
	public function key() {
		if( ! $this->check() ) {
			return false;
		}

		return $this->pointer;
	}

	/**
	 *
	 * @since 0.1
	 * @return void
	 */
	public function next() {
		$this->pointer++;
	}

	/**
	 *
	 * @since 0.1
	 * @return void
	 */
	public function rewind() {
		if( ! $this->check() ) {
			return false;
		}

		$this->pointer = 0;
	}

	/**
	 *
	 * @since 0.1
	 * @return boolean
	 */
	public function valid() {
		if( ! $this->check() ) {
			return false;
		}

		return $this->pointer < count( $this->items );
	}

	/**
	 * Allows additional conditions to be used for wheres.
	 *
	 * @since 0.1
	 *
	 * @param string|mixed[] $key   Either the key for what's needed or an array of conditions.
	 * @param mixed          $value The needed value.
	 * @return Collection
	 */
	public function where( $key, $value = null ) {
		if( $this->initialized ) {
			throw new \Exception( "Once a collection is initialized with data, it cannot be filtered from the database. Use ->filter() instead!" );
		}

		if( $value ) {
			$request = array( $key => $value );
		} else {
			$request = $key;
		}

		foreach( $request as $key => $value ) {
			$this->set( $key, $value );
			$method_name = 'set_' . $key;
		}

		return $this;
	}

	/**
	 * Adds a value to the arguments.
	 *
	 * @since 0.1
	 * @param string $key   The key for the argument.
	 * @param mixed  $value The value of the argument.
	 */
	protected function set( $key, $value ) {
		$this->args[ $key ] = $value;
	}

	/**
	 * Filters the results.
	 *
	 * @since 0.1
	 *
	 * @param mixed[] $filters The filters to apply.
	 * @return A cloned, filtered collection, so that the original is unaffected.
	 */
	public function filter( $filters ) {
		if( ! $this->initialized ) {
			$this->load();
		}

		$filtered = array();

		foreach( $this->items as $item ) {
			$ok = true;

			foreach( $filters as $key => $value ) {
				if( $item->$key != $value ) {
					$ok = false;
					break;
				}
			}

			if( $ok ) {
				$filtered[] = $item;
			}
		}

		if( empty( $filtered ) ) {
			return false;
		}

		$class_name = get_class( $this );
		return new $class_name( $filtered );
	}

	/**
	 * Sorts the collection.
	 *
	 * @since 0.1
	 *
	 * @param callbable $comparator A custom comparator.
	 */
	public function sort( $comparator = null ) {
		usort( $this->items, $comparator ? $comparator : array( $this, 'compare' ) );

		return $this;
	}

	/**
	 * Compares two items.
	 *
	 * @param Item $a The first item.
	 * @param Item $b The second item.
	 * @return bool
	 */
	public function compare( $a, $b ) {
		return $a->order() > $b->order();
	}

	/**
	 * By converting the class to a string, it can be checked if it has items.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function __toString() {
		$this->check();

		return $this->implode();
	}

	/**
	 * Implodes all items as strings.
	 *
	 * @since 0.1
	 *
	 * @param string $glue The glue to implode items through.
	 * @return string
	 */
	public function implode( $glue = ',' ) {
		return implode( $glue, $this->items );
	}

	/**
	 * Returns the element at a certain index.
	 *
	 * @since 0.1
	 *
	 * @param int $index The index of the element.
	 * @return Item The item at that index.
	 */
	public function at( $index ) {
		return $this->items[ $index ];
	}

	/**
	 * Returns the amount of elements in the collection.
	 *
	 * @since 0.1
	 *
	 * @return int
	 */
	public function count() {
		$this->check();
		
		return count( $this->items );
	}

	/**
	 * Returns an array of simple properties for var_dump() or print_r().
	 *
	 * @since 0.1
	 *
	 * @return mixed[]
	 */
	public function __debugInfo() {
		$this->check();

		return $this->items;
	}
}
