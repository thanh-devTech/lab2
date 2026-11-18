<?php
class CI_Page_Router
{
	public $pages = array();
	public $current_page = '';
	public function __construct()
	{

		add_action('init', array($this, 'init'), 10, 0);
		add_action('parse_request', array($this, 'parse_request'), 10, 0);
		add_filter('query_vars', array($this, 'add_query_vars'), 10, 1);
	}

	public function addPage($slug, $title, $protected = false)
	{
		$this->pages[$slug] = array(
			'title'     => $title,
			'protected' => $protected,
		);
	}

	public function init()
	{
		foreach ($this->pages as $slug => $arr) {

			add_rewrite_rule(
				'^' . $slug . '/?',
				'index.php?_patient_page=1&action=' .  $slug,
				'top'
			);
		}
		flush_rewrite_rules();
	}

	public function add_query_vars($vars)
	{
		$vars[] = '_patient_page';
		$vars[] = 'action';
		$vars[] = 'tab';

		return $vars;
	}

	public function parse_request()
	{
		global $wp;

		$this->current_page = isset($wp->query_vars['pagename']) ? $wp->query_vars['pagename'] : '';
		if (!empty($this->current_page) && !isset($this->pages[$this->current_page])) {
			$this->current_page = '';
		}
		if (isset($wp->query_vars['action']) && isset($wp->query_vars['_patient_page'])) {
			$this->current_page = $wp->query_vars['action'];
		}

		if (empty($this->current_page)) {
			foreach ($this->pages as $slug => $arr) {
				if (stripos($_SERVER['REQUEST_URI'], $slug) !== false) {
					$this->current_page = $slug;
					break;
				}
			}
		}

		if (isset($this->pages[$this->current_page])) {
			if (!defined('DONT_REDIRECT')) {
				define('DONT_REDIRECT', true);
			}
			$this->render();
		}
	}



	function render()
	{
		$page_info = $this->pages[$this->current_page];
		if ($page_info['protected'] && !is_user_logged_in()) {
			$login_url = wp_login_url($_SERVER['REQUEST_URI'], true);

			wp_redirect($login_url);
			exit;
		}
		include(TRCPPR_PATH . 'templates/page_header.php');
		include(TRCPPR_PATH . 'templates/' . str_replace('/', '_', $this->current_page) . '.php');
		include(TRCPPR_PATH . 'templates/page_footer.php');
		exit;
	}
}
