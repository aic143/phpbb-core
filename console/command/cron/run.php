<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited 
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\console\command\cron;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class run extends \phpbb\console\command\command
{
	/** @var \phpbb\cron\manager */
	protected $cron_manager;

	/** @var \phpbb\lock\db */
	protected $lock_db;

	/** @var \phpbb\user */
	protected $user;

	/**
	* Construct method
	*
	* @param \phpbb\cron\manager $cron_manager The cron manager containing
	*							the cron tasks to be executed.
	* @param \phpbb\lock\db $lock_db The lock for accessing database.
	* @param \phobb\user $user The user object (used to get language information)
	*/
	public function __construct(\phpbb\cron\manager $cron_manager, \phpbb\lock\db $lock_db, \phpbb\user $user)
	{
		$this->cron_manager = $cron_manager;
		$this->lock_db = $lock_db;
		$this->user = $user;
		parent::__construct();
	}

	/**
	* Sets the command name and description
	*
	* @return null
	*/
	protected function configure()
	{
		$this
			->setName('cron:run')
			->setDescription($this->user->lang('CLI_DESCR_CRON_RUN'))
			->addArgument('name', InputArgument::OPTIONAL, $this->user->lang('CLI_DESCR_CRON_ARG_RUN_1'))
		;
	}

	/**
	* Executes the function.
	*
	* Tries to acquire the cron lock, then if no argument has been given runs all ready cron tasks.
	* If the cron lock can not be obtained, an error message is printed
	*		and the exit status is set to 1.
	* If the verbose option is specified, each start of a task is printed.
	*		Otherwise there is no output.
	* If an argument is given to the command, only the task whose name matches the 
	*		argument will be started. If none exists, an error message is
	*		printed and the exit status is set to 2. Verbose option does nothing in 
	*		this case.
	*
	* @param InputInterface $input The input stream used to get the argument
	* @param OutputInterface $output The output stream, used for printing verbose-mode and error information.
	*
	* @return int 0 if all is ok, 1 if a lock error occured and -1 if no task matching the argument was found
	*/
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($this->lock_db->acquire())
		{
			$task_name = $input->getArgument('name');
			if ($task_name)
			{
				$task = $this->cron_manager->find_task($task_name);
				if ($task)
				{
					$task->run();
					return 0;
				}
				else
				{
					$output->writeln('<error>' . $this->user->lang('CRON_NO_TASK') . '</error>');
					return 2;
				}
			}
			else
			{
				$run_tasks = $this->cron_manager->find_all_ready_tasks();

				foreach ($run_tasks as $task)
				{
					if ($input->getOption('verbose'))
					{
						$output->writeln($this->user->lang('RUNNING_TASK', $task->get_name()));
					}

					$task->run();
				}
				$this->lock_db->release();

				return 0;
			}
		}
		else
		{
			$output->writeln('<error>' . $this->user->lang('CRON_LOCK_ERROR') . '</error>');
			return 1;
		}
	}
}
