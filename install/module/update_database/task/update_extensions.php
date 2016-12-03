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

namespace phpbb\install\module\update_database\task;

use phpbb\install\helper\container_factory;
use phpbb\install\helper\config;
use phpbb\install\helper\iohandler\iohandler_interface;
use phpbb\install\helper\update_helper;
use phpbb\install\task_base;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Finder\Finder;

/**
 * Installs extensions that exist in ext folder upon install
 */
class enable_extensions extends task_base
{
	/**
	 * @var config
	 */
	protected $install_config;

	/**
	 * @var iohandler_interface
	 */
	protected $iohandler;

	/** @var update_helper */
	protected $update_helper;

	/**
	 * @var \phpbb\config\db
	 */
	protected $config;

	/**
	 * @var \phpbb\log\log_interface
	 */
	protected $log;

	/**
	 * @var \phpbb\user
	 */
	protected $user;

	/** @var \phpbb\extension\manager */
	protected $extension_manager;

	/** @var Finder */
	protected $finder;

	/** @var string Extension table */
	protected $extension_table;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param container_factory			$container
	 * @param config					$install_config
	 * @param iohandler_interface		$iohandler
	 * @param $update_helper			$update_helper
	 * @param string					$phpbb_root_path phpBB root path
	 */
	public function __construct(container_factory $container, config $install_config, iohandler_interface $iohandler, update_helper $update_helper, $phpbb_root_path)
	{
		$this->install_config	= $install_config;
		$this->iohandler		= $iohandler;
		$this->extension_table = $container->get_parameter('tables.ext');

		$this->log				= $container->get('log');
		$this->user				= $container->get('user');
		$this->extension_manager = $container->get('ext.manager');
		$this->config			= $container->get('config');
		$this->db				= $container->get('dbal.conn');
		$this->finder = new Finder();
		$this->finder->in($phpbb_root_path . 'ext/')
			->ignoreUnreadableDirs()
			->depth('< 3')
			->files()
			->name('composer.json');

		// Make sure asset version exists in config. Otherwise we might try to
		// insert the assets_version setting into the database and cause a
		// duplicate entry error.
		if (!isset($this->config['assets_version']))
		{
			$this->config['assets_version'] = 0;
		}

		parent::__construct(true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$this->user->session_begin();
		$this->user->setup(array('common', 'acp/common', 'cli'));

		$input = new ArgvInput();
		$update_extensions = explode(',', $input->getArgument('update-extensions'));

		$update_info = $this->install_config->get('update_info_unprocessed', array());

		if (!empty($update_info))
		{
			// Find available extensions
			foreach ($this->finder as $file)
			{
				/** @var \SplFileInfo $file */
				$ext_name = preg_replace('#(.+[\\/\\\]ext[\\/\\\])(\w+)[\\/\\\](\w+)#', '$2/$3', dirname($file->getRealPath()));

				// Skip extensions that were not added or updated during update
				if (!count(preg_grep('#ext/' . $ext_name . '#', $update_info['files'])) &&
					!in_array($ext_name, $update_extensions) && $ext_name !== 'phpbb/viglink')
				{
					continue;
				}

				// Disable enabled extensions in order to run migrations if needed
				if ($this->extension_manager->is_enabled($ext_name))
				{
					$this->extension_manager->disable($ext_name);
				}

				if ($this->extension_manager->is_available($ext_name))
				{
					$this->extension_manager->enable($ext_name);
					$extensions = $this->get_extensions();

					if (isset($extensions[$ext_name]) && $extensions[$ext_name]['ext_active'])
					{
						// Create log
						$this->log->add('admin', ANONYMOUS, '', 'LOG_EXT_ENABLE', time(), array($ext_name));
					}
				}
				else
				{
					$this->iohandler->add_log_message('CLI_EXTENSION_ENABLE_FAILURE', array($ext_name));
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_step_count()
	{
		return 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_task_lang_name()
	{
		return 'TASK_UPDATE_EXTENSIONS';
	}

	/**
	 * Get extensions from database
	 *
	 * @return array List of extensions
	 */
	private function get_extensions()
	{
		$sql = 'SELECT *
			FROM ' . $this->extension_table;

		$result = $this->db->sql_query($sql);
		$extensions_row = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$extensions = array();

		foreach ($extensions_row as $extension)
		{
			$extensions[$extension['ext_name']] = $extension;
		}

		ksort($extensions);

		return $extensions;
	}
}
