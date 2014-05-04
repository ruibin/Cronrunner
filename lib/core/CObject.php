<?php

/**
 * ========================= abstract class CObject ============================
 *
 * @package Framework
 * @author ruibin<hcb0825@126.com>
 * @since 2012 - 08
 *
 * -----------------------------------------------------------------------------
 * It is the prototype of all components. Mainly Provides __set() and __get()
 * magic methods for child classes.
 * -----------------------------------------------------------------------------
 */
abstract class CObject
{
	#region magic methods

	/**
	 * Constructor.
	 */
	public function __construct($config=array())
	{
		$configurable = $this->configurable();
		if ($configurable == 'all') {
			foreach ($config as $_key=>$_value) {
				$this->$_key = $_value;
			}
		} elseif (is_array($configurable)) {
			foreach ($config as $_key=>$_value) {
				if (!in_array($_key, $configurable)) {
					trigger_error("'$_key' is not a configurable property.", E_USER_ERROR);
				}
				$this->$_key = $_value;
			}
		} else {
			trigger_error("Configurable method must return an array or string 'all'.", E_USER_ERROR);
		}
	}

	public function __set($key, $value)
	{
		$method = '_set' . ucfirst($key);
		if (method_exists($this, $method)) {
			$this->$method($value);
		} else {
			trigger_error("No writable property '$key'.", E_USER_ERROR);
		}
	}

	public function __get($key)
	{
		$method = '_get' . ucfirst($key);
		if (method_exists($this, $method)) {
			return $this->$method();
		} else {
			trigger_error("No readable property '$key'.", E_USER_ERROR);
		}
	}

	#end region

	/**
	 * Init component
	 */
	public function init() {}

	/**
	 * List of configurable properties.
	 * @return array|string
	 */
	protected function configurable() { return 'all'; }

	/**
	 * Whether object has readable property.
	 * @param string $key
	 * @return boolean
	 */
	public function hasReadableProperty($key)
	{
		return method_exists($this, '_get' . $key);
	}

	/**
	 * Whether object has writable property.
	 * @param string $key
	 * @return boolean
	 */
	public function hasWriteableProperty($key)
	{
		return method_exists($this, '_set' . $key);
	}
}
