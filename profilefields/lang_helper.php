<?php
/**
*
* @package phpBB3
* @copyright (c) 2014 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbb\profilefields;

/**
* Custom Profile Fields
* @package phpBB3
*/
class lang_helper
{
	/**
	* Array with the language option, grouped by field and language
	* @var array
	*/
	protected $options_lang = array();

	/**
	* Database object
	* @var \phpbb\db\driver\driver
	*/
	protected $db;

	/**
	* Construct
	*
	* @param	\phpbb\db\driver\driver	$db		Database object
	*/
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	* Get language entries for options and store them here for later use
	*/
	public function get_option_lang($field_id, $lang_id, $field_type, $preview_options)
	{
		if ($preview_options !== false)
		{
			$lang_options = (!is_array($preview_options)) ? explode("\n", $preview_options) : $preview_options;

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
	* Are language options set for this field?
	*
	* @param	int		$field_id		Database ID of the field
	* @param	int		$lang_id		ID of the language
	* @param	int		$field_value	Selected value of the field
	* @return boolean
	*/
	public function is_set($field_id, $lang_id = null, $field_value = null)
	{
		$is_set = isset($this->lang_helper->options_lang[$field_id]);

		if ($is_set && (!is_null($lang_id) || !is_null($field_value)))
		{
			$is_set = isset($this->lang_helper->options_lang[$field_id][$lang_id]);
		}

		if ($is_set && !is_null($field_value))
		{
			$is_set = isset($this->lang_helper->options_lang[$field_id][$lang_id][$field_value]);
		}

		return $is_set;
	}

	/**
	* Get the selected language string
	*
	* @param	int		$field_id		Database ID of the field
	* @param	int		$lang_id		ID of the language
	* @param	int		$field_value	Selected value of the field
	* @return string
	*/
	public function get($field_id, $lang_id, $field_value = null)
	{
		if (!is_null($field_value))
		{
			return $this->lang_helper->options_lang[$field_id][$lang_id];
		}

		return $this->lang_helper->options_lang[$field_id][$lang_id][$field_value];
	}
}
