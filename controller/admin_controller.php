<?php
/**
*
* @package PM Welcome
* @copyright (c) 2020 RMcGirr83
* @copyright BB3.MOBi (c) 2015 Anvar http://apwa.ru
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace apwa\pmwelcome\controller;

/*
* ignore
*/
use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;

class admin_controller
{
	/**
	 * sender data
	 */
	private $sender_info = array();

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string Custom form action */
	protected $u_action;

	/**
	* Constructor
	*
	* @param \phpbb\config\config									$config				Config object
	* @param \phpbb\config\db_text 									$config_text		Config text object
	* @param \phpbb\db\driver\driver_interface						$db					Database object
	* @param \phpbb\language\language								$language			Language object
	* @param \phpbb\log\log											$log				Log object
	* @param \phpbb\request\request									$request			Request object
	* @param \phpbb\template\template								$template			Template object
	* @param \phpbb\user											$user				User object
	* @param string													$phpbb_root_path		phpBB root path
	* @param string													$php_ext			phpEx
	* @access public
	*/
	public function __construct(
			config $config,
			db_text $config_text,
			driver_interface $db,
			language $language,
			log $log,
			request $request,
			template $template,
			user $user,
			$phpbb_root_path,
			$php_ext)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		if (!function_exists('display_custom_bbcodes'))
		{
			include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		}
		if (!function_exists('validate_data'))
		{
			include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}
	}

	public function display_options()
	{
		$this->language->add_lang('posting');

		// Create a form key for preventing CSRF attacks
		add_form_key('pmwelcome_settings');
		$error = array();

		$pmwelcome_data		= $this->config_text->get_array(array(
			'pmwelcome_post_text',
			'pmwelcome_text_bitfield',
			'pmwelcome_text_uid',
			'pmwelcome_text_flags',
		));

		$pmwelcome_post_text		= $pmwelcome_data['pmwelcome_post_text'];
		$pmwelcome_text_bitfield	= $pmwelcome_data['pmwelcome_text_bitfield'];
		$pmwelcome_text_uid			= $pmwelcome_data['pmwelcome_text_uid'];
		$pmwelcome_text_flags		= $pmwelcome_data['pmwelcome_text_flags'];

		$sender_count = (int) $this->pm_welcome_sender_count();

		$sender_info = $this->pm_welcome_user_name($this->request->variable('pmwelcome_user', $this->config['pmwelcome_user']));

		if (!isset($sender_info['error']))
		{
			$user_link = '<a href="' . append_sid("{$this->phpbb_root_path}memberlist.$this->php_ext", 'mode=viewprofile&amp;u=' . $sender_info['user_id']) . '" target="_blank">' . $sender_info['username'] . '</a>';
		}

		$pmwelcome_subject = $this->request->variable('pmwelcome_subject', $this->config['pmwelcome_subject'], true);
		$pmwelcome_edit = generate_text_for_edit($pmwelcome_post_text, $pmwelcome_text_uid, $pmwelcome_text_flags);
		$pmwelcome_post_text = $this->request->variable('pmwelcome_post_text', $pmwelcome_post_text, true);

		if ($this->request->is_set_post('submit')  || $this->request->is_set_post('preview'))
		{
			if (!check_form_key('pmwelcome_settings'))
			{
				$error[] = $this->language->lang('FORM_INVALID');
			}

			if (isset($sender_info['error']))
			{
				$error[] = $sender_info['error'];
			}

			if (utf8_clean_string($pmwelcome_post_text) === '')
			{
				$error[] = $this->language->lang('TOO_SHORT_PMWELCOME_POST_TEXT');
			}

			if (utf8_clean_string($pmwelcome_subject) === '')
			{
				$error[] = $this->language->lang('TOO_SHORT_PMWELCOME_SUBJECT');
			}

			if (empty($error) && $this->request->is_set_post('submit'))
			{
				$this->config_text->set_array(array(
					'pmwelcome_post_text'		=> $pmwelcome_post_text,
					'pmwelcome_text_bitfield'	=> $pmwelcome_text_bitfield,
					'pmwelcome_text_uid'		=> $pmwelcome_text_uid,
					'pmwelcome_text_flags'		=> $pmwelcome_text_flags,
				));

				$this->config->set('pmwelcome_sender', (string) $sender_info['username']);
				$this->config->set('pmwelcome_user', (int) $sender_info['user_id']);
				$this->config->set('pmwelcome_subject', $pmwelcome_subject);

				// and an entry into the log table
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PMWELCOME_CONFIG_UPDATE');

				meta_refresh(5, $this->u_action);
				trigger_error($this->language->lang('ACP_PMWELCOME_CONFIG_SAVED') . adm_back_link($this->u_action));
			}
		}

		$pmwelcome_text_preview = '';
		if ($this->request->is_set_post('preview'))
		{
			$pmwelcome_text_preview = (!isset($sender_info['error'])) ? str_replace('{SENDER}', $sender_info['username'], $pmwelcome_post_text) : $pmwelcome_post_text;
			generate_text_for_storage(
				$pmwelcome_text_preview,
				$pmwelcome_text_uid	,
				$pmwelcome_text_bitfield,
				$pmwelcome_text_flags,
				!$this->request->variable('disable_bbcode', false),
				!$this->request->variable('disable_magic_url', false),
				!$this->request->variable('disable_smilies', false)
			);
			$pmwelcome_text_preview = generate_text_for_display($pmwelcome_text_preview, $pmwelcome_text_uid, $pmwelcome_text_bitfield, $pmwelcome_text_flags);
			$pmwelcome_edit = generate_text_for_edit($pmwelcome_post_text, $pmwelcome_text_uid, $pmwelcome_text_flags);
		}

		$this->template->assign_vars(array(
			'PMWELCOME_ERROR'			=> (sizeof($error)) ? implode('<br />', $error) : false,

			'PMWELCOME_EDIT'			=> $pmwelcome_edit['text'],
			'PMWELCOME_TEXT_PREVIEW'	=> $pmwelcome_text_preview,
			'SENDER_MAX'				=> $sender_count,
			'SENDER_LINK'				=> $user_link,

			'PMWELCOME_USER'			=> $sender_info['user_id'],
			'PMWELCOME_SUBJECT'			=> $pmwelcome_subject,

			'S_BBCODE_ALLOWED'		=> true,
			'S_SMILIES_ALLOWED'		=> true,
			'S_BBCODE_IMG'			=> true,
			'S_BBCODE_FLASH'		=> false,
			'S_LINKS_ALLOWED'		=> true,

			'U_ACTION'				=> $this->u_action,
		));
		// Assigning custom bbcodes
		display_custom_bbcodes();
	}

	/**
	* pm_welcome_user_name
	*
	* @param sender_id				sender user id
	* @return array					array of user info or error if not found
	* @access private
	*/
	private function pm_welcome_user_name($sender_id)
	{
		$sender = array();

		$sql = 'SELECT user_id, username
			FROM ' . USERS_TABLE . "
			WHERE user_id = " . (int) $sender_id;
		$result = $this->db->sql_query($sql);
		$sender = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$sender['username'])
		{
			$sender['error'] = $this->language->lang('NO_USER');
		}

		return $sender;
	}

	/**
	* pm_welcome_sender_count
	*
	* @return int				a count of users of the forum used to ensure validation of sender
	* @access private
	*/
	private function pm_welcome_sender_count()
	{
		$sender_count = '';

		$sql = 'SELECT COUNT(user_id) as user_count
			FROM ' . USERS_TABLE;
		$result = $this->db->sql_query($sql);
		$sender_count = $this->db->sql_fetchfield('user_count');
		$this->db->sql_freeresult($result);

		return (int) $sender_count;
	}
	/**
	 * Set page url
	 *
	 * @param string $u_action Custom form action
	 * @return null
	 * @access public
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
