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

namespace phpbb\console\command\user;

use phpbb\exception\runtime_exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class add extends \phpbb\console\command\command
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\passwords\manager */
	protected $password_manager;

	/**
	 * phpBB root path
	 *
	 * @var string
	 */
	protected $phpbb_root_path;

	/**
	 * PHP extension.
	 *
	 * @var string
	 */
	protected $php_ext;

	/**
	 * Construct method
	 *
	 * @param \phpbb\user                       $user
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\config\config              $config
	 * @param \phpbb\language\language          $language
	 * @param \phpbb\passwords\manager          $password_manager
	 * @param string                            $phpbb_root_path
	 * @param string                            $php_ext
	 */
	public function __construct(\phpbb\user $user, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\language\language $language, \phpbb\passwords\manager $password_manager, $phpbb_root_path, $php_ext)
	{
		$this->db = $db;
		$this->config = $config;
		$this->language = $language;
		$this->password_manager = $password_manager;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$this->language->add_lang('ucp');
		parent::__construct($user);
	}

	/**
	 * Sets the command name and description
	 *
	 * @return null
	 */
	protected function configure()
	{
		$this
			->setName('user:add')
			->setDescription($this->language->lang('CLI_DESCRIPTION_USER_ADD'))
			->addOption('username', null, InputOption::VALUE_REQUIRED, $this->language->lang('CLI_DESCRIPTION_USER_ADD_OPTION_USERNAME'))
			->addOption('password', null, InputOption::VALUE_REQUIRED, $this->language->lang('CLI_DESCRIPTION_USER_ADD_OPTION_PASSWORD'))
			->addOption('email', null, InputOption::VALUE_REQUIRED, $this->language->lang('CLI_DESCRIPTION_USER_ADD_OPTION_EMAIL'))
			->addOption('send-email', null, InputOption::VALUE_NONE, $this->language->lang('CLI_CONFIG_PRINT_WITHOUT_NEWLINE'))
		;
	}

	/**
	 * Executes the command user:add
	 *
	 * Adds a new user to the database. If options are not provided, it will ask for the username, password and email.
	 * User is added to the registered user group. Language and timezone default to $config settings.
	 *
	 * @param InputInterface  $input  The input stream used to get the options
	 * @param OutputInterface $output The output stream, used to print messages
	 *
	 * @return int 0 if all is well, 1 if any errors occurred
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		$helper = $this->getHelper('question');

		$data = array(
			'username'     => $input->getOption('username'),
			'new_password' => $input->getOption('password'),
			'email'        => $input->getOption('email'),
		);

		if (!$data['username'])
		{
			$question = new Question($this->ask_user('USERNAME'), null);
			$data['username'] = $helper->ask($input, $output, $question);
		}

		if (!$data['new_password'])
		{
			$self = $this;
			$question = new Question($this->ask_user('PASSWORD'));
			$question->setValidator(function ($value) use ($self, $helper, $input, $output) {
				$question = new Question($self->ask_user('CONFIRM_PASSWORD'));
				$question->setHidden(true);

				$confirm = $helper->ask($input, $output, $question);
				if ($confirm != $value)
				{
					throw new runtime_exception($self->language->lang('NEW_PASSWORD_ERROR'));
				}
				return $value;
			});
			$question->setHidden(true);
			$question->setMaxAttempts(5);

			$data['new_password'] = $helper->ask($input, $output, $question);
		}

		if (!$data['email'])
		{
			$question = new Question($this->ask_user('EMAIL_ADDRESS'), null);
			$data['email'] = $helper->ask($input, $output, $question);
		}

		try
		{
			$this->validate_user_data($data);
		}
		catch (runtime_exception $e)
		{
			$io->error($e->getMessage());
			return 1;
		}

		try
		{
			$group_id = $this->get_group_id();
		}
		catch (runtime_exception $e)
		{
			$io->error($e->getMessage());
			return 1;
		}

		$user_row = array(
			'username'      => $data['username'],
			'user_password' => $this->password_manager->hash($data['new_password']),
			'user_email'    => $data['email'],
			'group_id'      => $group_id,
			'user_timezone' => $this->config['board_timezone'],
			'user_lang'     => $this->config['default_lang'],
			'user_type'     => USER_NORMAL,
			'user_regdate'  => time(),
		);

		if (!function_exists('user_add'))
		{
			require($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}

		$user_id = (int) user_add($user_row);

		if ($input->getOption('send-email') && $this->config['email_enable'])
		{
			$this->send_activation_email($user_id, $data);
		}

		$io->success($this->language->lang('SUCCESS_ADD_USER', $data['username']));

		return 0;
	}

	/**
	 * Validate the submitted user data
	 *
	 * @param array $data The user data array
	 * @throws runtime_exception if any data fails validation
	 * @return null
	 */
	protected function validate_user_data($data)
	{
		if (!function_exists('validate_data'))
		{
			require($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}

		$error = validate_data($data, array(
			'username'     => array(
				array('string', false, $this->config['min_name_chars'], $this->config['max_name_chars']),
				array('username', '')),
			'new_password' => array(
				array('string', false, $this->config['min_pass_chars'], $this->config['max_pass_chars']),
				array('password')),
			'email'        => array(
				array('string', false, 6, 60),
				array('user_email')),
		));

		if ($error)
		{
			throw new runtime_exception(implode("\n", array_map(array($this->language, 'lang'), $error)));
		}
	}

	/**
	 * Get the group id
	 *
	 * Go and find in the database the group_id corresponding to 'REGISTERED'
	 *
	 * @throws runtime_exception if the group id does not exist in database.
	 * @return null
	 */
	protected function get_group_id()
	{
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape('REGISTERED') . "'
				AND group_type = " . GROUP_SPECIAL;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row || !$row['group_id'])
		{
			throw new runtime_exception($this->language->lang('NO_GROUP'));
		}

		return $row['group_id'];
	}

	/**
	 * Send account activation email
	 *
	 * @param int   $user_id The new user's id
	 * @param array $data    The user data array
	 * @return null
	 */
	protected function send_activation_email($user_id, $data)
	{
		if ($this->config['require_activation'] == USER_ACTIVATION_SELF)
		{
			$email_template = 'user_welcome_inactive';
			$user_actkey = gen_rand_string(mt_rand(6, 10));
		}
		else if ($this->config['require_activation'] == USER_ACTIVATION_ADMIN)
		{
			$email_template = 'admin_welcome_inactive';
			$user_actkey = gen_rand_string(mt_rand(6, 10));
		}
		else
		{
			$email_template = 'user_welcome';
			$user_actkey = '';
		}

		if (!class_exists('messenger'))
		{
			require($this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext);
		}

		$messenger = new \messenger(false);

		$messenger->template($email_template, $this->user->lang_name);

		$messenger->to($data['email'], $data['username']);

		$messenger->anti_abuse_headers($this->config, $this->user);

		$messenger->assign_vars(array(
				'WELCOME_MSG' => htmlspecialchars_decode($this->language->lang('WELCOME_SUBJECT', $this->config['sitename'])),
				'USERNAME'    => htmlspecialchars_decode($data['username']),
				'PASSWORD'    => htmlspecialchars_decode($data['new_password']),
				'U_ACTIVATE'  => generate_board_url() . "/ucp.{$this->php_ext}?mode=activate&u=$user_id&k=$user_actkey")
		);

		$messenger->send(NOTIFY_EMAIL);
	}

	/**
	 * Helper to translate questions to the user
	 *
	 * @param string $key The language key
	 * @return string The language key translated with a colon and space appended
	 */
	protected function ask_user($key)
	{
		return $this->language->lang($key) . $this->language->lang('COLON') . ' ';
	}
}
