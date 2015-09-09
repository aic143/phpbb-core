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

namespace phpbb\files\types;

use \phpbb\files\filespec;
use \phpbb\files\upload;
use \phpbb\language\language;

abstract class base implements type_interface
{
	/** @var language */
	protected $language;

	/** @var upload */
	protected $upload;

	/**
	 * Check if upload exceeds maximum file size
	 *
	 * @param filespec $file Filespec object
	 *
	 * @return filespec Returns same filespec instance
	 */
	public function check_upload_size($file)
	{
		// PHP Upload filesize exceeded
		if ($file->get('filename') == 'none')
		{
			$max_filesize = @ini_get('upload_max_filesize');
			$unit = 'MB';

			if (!empty($max_filesize))
			{
				$unit = strtolower(substr($max_filesize, -1, 1));
				$max_filesize = (int) $max_filesize;

				$unit = ($unit == 'k') ? 'KB' : (($unit == 'g') ? 'GB' : 'MB');
			}

			$file->error[] = (empty($max_filesize)) ? $this->language->lang($this->upload->error_prefix . 'PHP_SIZE_NA') : $this->language->lang($this->upload->error_prefix . 'PHP_SIZE_OVERRUN', $max_filesize, $this->language->lang($unit));
		}

		return $file;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_upload(upload $upload)
	{
		$this->upload = $upload;

		return $this;
	}
}
