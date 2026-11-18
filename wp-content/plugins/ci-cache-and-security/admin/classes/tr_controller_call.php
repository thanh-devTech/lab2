<?php

class Tr_Controller_Call{
	var $dir;
	var $controller;
	var $action;
	var $file;

	public function __construct($dir)
	{
		$this->dir = $dir;
		$this->controller = isset($_REQUEST['tr_control'])? $_REQUEST['tr_control'] : '';
		$this->action = isset($_REQUEST['action'])? $_REQUEST['action'] : '';
		$this->file = $this->dir.'/inc/'.strtolower($this->controller).'.php';
		$this->class_name = $this->controller;

		if(is_file($this->file))
		{
			include($this->file);
			if(class_exists($this->class_name))
			{
				$this->con = new $this->class_name();
				if(is_admin())
				{
					add_action('admin_init',array(&$this->con,'admin_'.$this->action),99);
				}else{
					add_action('init',array(&$this->con,'front_'.$this->action),99);
				}
			}
		}
	}

}