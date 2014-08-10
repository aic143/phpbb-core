<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\captcha;

/**
* A small class for 3.0.x (no autoloader in 3.0.x)
*/
class factory
{
	/**
	* @var \Symfony\Component\DependencyInjection\ContainerInterface
	*/
	private $container;

	/**
	* @var \phpbb\di\service_collection
	*/
	private $plugins;

	/**
	* Constructor
	*
	* @param \Symfony\Component\DependencyInjection\ContainerInterface $container
	* @param \phpbb\di\service_collection                              $plugins
	*/
	public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container,\phpbb\di\service_collection $plugins)
	{
		$this->container = $container;
		$this->plugins = $plugins;
	}

	//static public function get_instance($name)

	public function get_instance($name)
	{
		return $this->container->get($name);
	}

	/**
	* Call the garbage collector
	*/
	function garbage_collect($name)
	{
		$captcha = $this->get_instance($name);
		$captcha->garbage_collect(0);
	}

	/**
	* return a list of all registered CAPTCHA plugins
	*/
	function get_captcha_types()
	{
		$captchas = array(
			'available'		=> array(),
			'unavailable'	=> array(),
		);

		foreach ($this->plugins as $plugin => $plugin_instance)
		{
			if ($plugin_instance->is_available())
			{
				$captchas['available'][$plugin] = $plugin_instance->get_name();
			}
			else
			{
				$captchas['unavailable'][$plugin] = $plugin_instance->get_name();
			}
		}

		return $captchas;
	}
}
