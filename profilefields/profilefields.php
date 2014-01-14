<?php
/**
*
* @package phpBB3
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbb\profilefields;

/**
* Custom Profile Fields
* @package phpBB3
*/
class profilefields
{
	var $profile_types = array(FIELD_INT => 'int', FIELD_STRING => 'string', FIELD_TEXT => 'text', FIELD_BOOL => 'bool', FIELD_DROPDOWN => 'dropdown', FIELD_DATE => 'date');
	var $profile_cache = array();
	var $options_lang = array();

	/**
	*
	*/
	public function __construct($auth, $db, /** @todo: */ $phpbb_container, $request, $template, $user)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->container = $phpbb_container;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
	}

	/**
	* Assign editable fields to template, mode can be profile (for profile change) or register (for registration)
	* Called by ucp_profile and ucp_register
	* @access public
	*/
	function generate_profile_fields($mode, $lang_id)
	{
		$sql_where = '';
		switch ($mode)
		{
			case 'register':
				// If the field is required we show it on the registration page
				$sql_where .= ' AND f.field_show_on_reg = 1';
			break;

			case 'profile':
				// Show hidden fields to moderators/admins
				if (!$this->auth->acl_gets('a_', 'm_') && !$this->auth->acl_getf_global('m_'))
				{
					$sql_where .= ' AND f.field_show_profile = 1';
				}
			break;

			default:
				trigger_error('Wrong profile mode specified', E_USER_ERROR);
			break;
		}

		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . " f
			WHERE f.field_active = 1
				$sql_where
				AND l.lang_id = $lang_id
				AND l.field_id = f.field_id
			ORDER BY f.field_order";
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			// Return templated field
			$tpl_snippet = $this->process_field_row('change', $row);

			// Some types are multivalue, we can't give them a field_id as we would not know which to pick
			$type = (int) $row['field_type'];

			$this->template->assign_block_vars('profile_fields', array(
				'LANG_NAME'		=> $row['lang_name'],
				'LANG_EXPLAIN'	=> $row['lang_explain'],
				'FIELD'			=> $tpl_snippet,
				'FIELD_ID'		=> ($type == FIELD_DATE || ($type == FIELD_BOOL && $row['field_length'] == '1')) ? '' : 'pf_' . $row['field_ident'],
				'S_REQUIRED'	=> ($row['field_required']) ? true : false)
			);
		}
		$this->db->sql_freeresult($result);
	}

	/**
	* Build profile cache, used for display
	* @access private
	*/
	function build_cache()
	{
		$this->profile_cache = array();

		// Display hidden/no_view fields for admin/moderator
		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . ' f
			WHERE l.lang_id = ' . $this->user->get_iso_lang_id() . '
				AND f.field_active = 1 ' .
				((!$this->auth->acl_gets('a_', 'm_') && !$this->auth->acl_getf_global('m_')) ? '	AND f.field_hide = 0 ' : '') . '
				AND f.field_no_view = 0
				AND l.field_id = f.field_id
			ORDER BY f.field_order';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->profile_cache[$row['field_ident']] = $row;
		}
		$this->db->sql_freeresult($result);
	}

	/**
	* Get language entries for options and store them here for later use
	*/
	function get_option_lang($field_id, $lang_id, $field_type, $preview)
	{
		if ($preview)
		{
			$lang_options = (!is_array($this->vars['lang_options'])) ? explode("\n", $this->vars['lang_options']) : $this->vars['lang_options'];

			foreach ($lang_options as $num => $var)
			{
				$this->options_lang[$field_id][$lang_id][($num + 1)] = $var;
			}
		}
		else
		{
			$sql = 'SELECT option_id, lang_value
				FROM ' . PROFILE_FIELDS_LANG_TABLE . "
					WHERE field_id = $field_id
					AND lang_id = $lang_id
					AND field_type = $field_type
				ORDER BY option_id";
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->options_lang[$field_id][$lang_id][($row['option_id'] + 1)] = $row['lang_value'];
			}
			$this->db->sql_freeresult($result);
		}
	}

	/**
	* Submit profile field for validation
	* @access public
	*/
	function submit_cp_field($mode, $lang_id, &$cp_data, &$cp_error)
	{
		$sql_where = '';
		switch ($mode)
		{
			case 'register':
				// If the field is required we show it on the registration page
				$sql_where .= ' AND f.field_show_on_reg = 1';
			break;

			case 'profile':
				// Show hidden fields to moderators/admins
				if (!$this->auth->acl_gets('a_', 'm_') && !$this->auth->acl_getf_global('m_'))
				{
					$sql_where .= ' AND f.field_show_profile = 1';
				}
			break;

			default:
				trigger_error('Wrong profile mode specified', E_USER_ERROR);
			break;
		}

		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . " f
			WHERE l.lang_id = $lang_id
				AND f.field_active = 1
				$sql_where
				AND l.field_id = f.field_id
			ORDER BY f.field_order";
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$profile_field = $this->container->get('profilefields.type.' . $this->profile_types[$row['field_type']]);
			$cp_data['pf_' . $row['field_ident']] = $profile_field->get_profile_field($row);
			$check_value = $cp_data['pf_' . $row['field_ident']];

			if (($cp_result = $profile_field->validate_profile_field($check_value, $row)) !== false)
			{
				// If the result is not false, it's an error message
				$cp_error[] = $cp_result;
			}
		}
		$this->db->sql_freeresult($result);
	}

	/**
	* Update profile field data directly
	*/
	function update_profile_field_data($user_id, &$cp_data)
	{
		if (!sizeof($cp_data))
		{
			return;
		}

		switch ($this->db->sql_layer)
		{
			case 'oracle':
			case 'firebird':
			case 'postgres':
				$right_delim = $left_delim = '"';
			break;

			case 'sqlite':
			case 'mssql':
			case 'mssql_odbc':
			case 'mssqlnative':
				$right_delim = ']';
				$left_delim = '[';
			break;

			case 'mysql':
			case 'mysql4':
			case 'mysqli':
				$right_delim = $left_delim = '`';
			break;
		}

		// use new array for the UPDATE; changes in the key do not affect the original array
		$cp_data_sql = array();
		foreach ($cp_data as $key => $value)
		{
			// Firebird is case sensitive with delimiter
			$cp_data_sql[$left_delim . (($this->db->sql_layer == 'firebird' || $this->db->sql_layer == 'oracle') ? strtoupper($key) : $key) . $right_delim] = $value;
		}

		$sql = 'UPDATE ' . PROFILE_FIELDS_DATA_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $cp_data_sql) . "
			WHERE user_id = $user_id";
		$this->db->sql_query($sql);

		if (!$this->db->sql_affectedrows())
		{
			$cp_data_sql['user_id'] = (int) $user_id;

			$this->db->sql_return_on_error(true);

			$sql = 'INSERT INTO ' . PROFILE_FIELDS_DATA_TABLE . ' ' . $this->db->sql_build_array('INSERT', $cp_data_sql);
			$this->db->sql_query($sql);

			$this->db->sql_return_on_error(false);
		}
	}

	/**
	* Assign fields to template, used for viewprofile, viewtopic and memberlist (if load setting is enabled)
	* This is directly connected to the user -> mode == grab is to grab the user specific fields, mode == show is for assigning the row to the template
	* @access public
	*/
	function generate_profile_fields_template($mode, $user_id = 0, $profile_row = false)
	{
		if ($mode == 'grab')
		{
			if (!is_array($user_id))
			{
				$user_id = array($user_id);
			}

			if (!sizeof($this->profile_cache))
			{
				$this->build_cache();
			}

			if (!sizeof($user_id))
			{
				return array();
			}

			$sql = 'SELECT *
				FROM ' . PROFILE_FIELDS_DATA_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $user_id));
			$result = $this->db->sql_query($sql);

			$field_data = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$field_data[$row['user_id']] = $row;
			}
			$this->db->sql_freeresult($result);

			$user_fields = array();

			$user_ids = $user_id;

			// Go through the fields in correct order
			foreach (array_keys($this->profile_cache) as $used_ident)
			{
				foreach ($field_data as $user_id => $row)
				{
					$user_fields[$user_id][$used_ident]['value'] = $row['pf_' . $used_ident];
					$user_fields[$user_id][$used_ident]['data'] = $this->profile_cache[$used_ident];
				}

				foreach ($user_ids as $user_id)
				{
					if (!isset($user_fields[$user_id][$used_ident]) && $this->profile_cache[$used_ident]['field_show_novalue'])
					{
						$user_fields[$user_id][$used_ident]['value'] = '';
						$user_fields[$user_id][$used_ident]['data'] = $this->profile_cache[$used_ident];
					}
				}
			}

			return $user_fields;
		}
		else if ($mode == 'show')
		{
			// $profile_row == $user_fields[$row['user_id']];
			$tpl_fields = array();
			$tpl_fields['row'] = $tpl_fields['blockrow'] = array();

			foreach ($profile_row as $ident => $ident_ary)
			{
				$profile_field = $this->container->get('profilefields.type.' . $this->profile_types[$row['field_type']]);
				$value = $profile_field->get_profile_value($ident_ary['value'], $ident_ary['data']);

				if ($value === NULL)
				{
					continue;
				}

				$tpl_fields['row'] += array(
					'PROFILE_' . strtoupper($ident) . '_VALUE'	=> $value,
					'PROFILE_' . strtoupper($ident) . '_TYPE'	=> $ident_ary['data']['field_type'],
					'PROFILE_' . strtoupper($ident) . '_NAME'	=> $ident_ary['data']['lang_name'],
					'PROFILE_' . strtoupper($ident) . '_EXPLAIN'=> $ident_ary['data']['lang_explain'],

					'S_PROFILE_' . strtoupper($ident)			=> true
				);

				$tpl_fields['blockrow'][] = array(
					'PROFILE_FIELD_VALUE'	=> $value,
					'PROFILE_FIELD_TYPE'	=> $ident_ary['data']['field_type'],
					'PROFILE_FIELD_NAME'	=> $ident_ary['data']['lang_name'],
					'PROFILE_FIELD_EXPLAIN'	=> $ident_ary['data']['lang_explain'],

					'S_PROFILE_' . strtoupper($ident)		=> true
				);
			}

			return $tpl_fields;
		}
		else
		{
			trigger_error('Wrong mode for custom profile', E_USER_ERROR);
		}
	}

	/**
	* Return Templated value/field. Possible values for $mode are:
	* change == user is able to set/enter profile values; preview == just show the value
	* @access private
	*/
	function process_field_row($mode, $profile_row)
	{
		$preview = ($mode == 'preview') ? true : false;

		// set template filename
		$this->template->set_filenames(array(
			'cp_body'		=> 'custom_profile_fields.html',
		));

		// empty previously filled blockvars
		foreach ($this->profile_types as $field_case => $field_type)
		{
			$this->template->destroy_block_vars($field_type);
		}

		// Assign template variables
		$profile_field = $this->container->get('profilefields.type.' . $this->profile_types[$profile_row['field_type']]);
		$profile_field->generate_field($profile_row, $preview);

		// Return templated data
		return $this->template->assign_display('cp_body');
	}

	/**
	* Build Array for user insertion into custom profile fields table
	*/
	function build_insert_sql_array($cp_data)
	{
		$sql_not_in = array();
		foreach ($cp_data as $key => $null)
		{
			$sql_not_in[] = (strncmp($key, 'pf_', 3) === 0) ? substr($key, 3) : $key;
		}

		$sql = 'SELECT f.field_type, f.field_ident, f.field_default_value, l.lang_default_value
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . ' f
			WHERE l.lang_id = ' . $this->user->get_iso_lang_id() . '
				' . ((sizeof($sql_not_in)) ? ' AND ' . $this->db->sql_in_set('f.field_ident', $sql_not_in, true) : '') . '
				AND l.field_id = f.field_id';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$profile_field = $this->container->get('profilefields.type.' . $this->profile_types[$row['field_type']]);
			$cp_data['pf_' . $row['field_ident']] = $profile_field->get_default_field_value($row);
		}
		$this->db->sql_freeresult($result);

		return $cp_data;
	}
}
