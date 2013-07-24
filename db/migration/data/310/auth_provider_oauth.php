<?php
/**
*
* @package migration
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License v2
*
*/

class phpbb_db_migration_data_310_auth_provider_oauth extends phpbb_db_migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'auth_provider_oauth');
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'oauth_token'	=> array(
					'COLUMNS' => array(
						'user_id'			=> array('UINT', 0), // phpbb_users.user_id
						'session_id'		=> array('CHAR:32', ''), // phpbb_sessions.session_id used only when user_id not set
						'oauth_provider'	=> array('VCHAR'), // Name of the OAuth provider
						'oauth_token'		=> array('TEXT_UNI'), // Serialized token
					),
					'KEYS' => array(
						'user_id'			=> array('INDEX', 'user_id'),
						'oauth_provider'	=> array('INDEX', 'oauth_provider'),
					),
				),
				$this->table_prefix . 'oauth_accounts'	=> array(
					'COLUMNS' => array(
						'user_id'			=> array('UINT', 0),
						'oauth_provider'	=> array('VCHAR'),
						'oauth_provider_id'	=> array('TEXT_UNI'),
					),
					'PRIMARY_KEY' => array(
						'user_id',
						'oauth_provider',
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'auth_provider_oauth',
			),
		);
	}
}
