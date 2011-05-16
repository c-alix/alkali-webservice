<?php

class ExampleService extends Webservice
{
	function __construct()
	{
		$method = parent::init();

		try {
			$this->method = $method;
			$this->$method();
		}
		catch (Exception $e)
		{
			$this->res['error'] = $e->getMessage();
			$this->send();
			exit;
		}
	}

	function do_hello()
	{
		$this->description('Returns a hello world');
		$this->requires('fname:str');
		
		$this->res = array("hello ".$this->input->fname);
		
		$this->send('hello');
	}
}

?>