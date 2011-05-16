<?php

/********************************

Alka.li Webservice Framework


for .htaccess

<IfModule mod_rewrite.c>
    RewriteEngine On
    #RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>

*********************************/

$api = '';
if (isset($_SERVER['PATH_INFO']))
{
	$args = explode('/',$_SERVER['PATH_INFO']);
	$api = $args[1];
}

switch ($api)
{
	case 'example':
		include 'example_service.php';
		$ob = new ExampleService;
		break;

	default:
		showWelcomePage();
}

function showWelcomePage()
{
	include 'welcome.htm';
	exit;
}

class WebserviceInput
{
}

/**
 * Any method which begins with do_ is accessible via http get
 * Other methods are not accessible
 *
 */
abstract class Webservice
{
	public $serviceName;
	public $method;
	public $args;

	/**
	 * Parses PATH_INFO, Creates args array
	 * Returns method name
	 *
	 * @return unknown
	 */
	protected function init()
	{
		$this->input = new WebserviceInput;
		
		if (isset($_SERVER['PATH_INFO']))
		{
			$this->args = explode('/',$_SERVER['PATH_INFO']);
			array_shift($this->args); // remove the /
			$this->serviceName = array_shift($this->args); // remove the api name
			
			if (isset($this->args[0]))
			{
				$this->action = strtolower($this->args[0]);
			}

			array_shift($this->args); // remove the method name
		}

		if (!empty($this->action))
		{
			$method = "do_$this->action";

			if (!method_exists($this,$method))
			{
				$this->msgDie('"'.$this->action.'" is not a valid method');
			}
		}
		else
		{
			$this->showServiceIndex();
		}

		return $method;
	}

	protected function description($str)
	{
		$this->description = $str;
	}
	
	/**
	 * Receives a string description of desired params
	 * Maps object properties from $this->args based on $str_vars
	 * Calls showHelp if params are inadequate
	 *
	 * @param unknown_type $str_vars
	 */
	protected function requires($str_vars)
	{
		$vars = explode(',',$str_vars);

		$var_cnt = count($vars);

		for ($i=0; $i < $var_cnt; $i++)
		{
			$opt = false;
			$optstr = "";

			list ($var,$type) = explode(':',trim($vars[$i]));

			if (strlen($type) > 3)
			{
				$type = substr($type,0,3);
				$opt = true;
			}

			if ($opt)
			{
				$optstr = " optional:";
			}

			$this->required[] = "$optstr$var ($type)";

			if (isset($this->args[$i]))
			{
				$this->input->$var = $this->args[$i];
			}

			if (isset($_GET[$var]))
			{
				$this->input->$var = $_GET[$var];
			}
			if (isset($_POST[$var]))
			{
				$this->input->$var = $_POST[$var];
			}

			if (!$opt && $this->input->$var == '')
			{
				$this->missing[] = $var;
			}
			elseif (!$this->checkType($this->input->$var,$type,$opt))
			{
				$this->type_mismatch[] = $var;
			}
		}

		if (is_array($this->missing) || is_array($this->type_mismatch))
		{
			$this->showHelp();
		}
	}

	protected function checkType($var,$str_type,$opt)
	{
		if ($opt && $var == '') //return if optional var is empty
			return true;

		switch ($str_type)
		{
			case 'str':
				return is_string($var);
			case 'int':
				return is_numeric($var);
		}
	}

	protected function showServiceIndex()
	{
		foreach (get_class_methods($this) as $m)
		{
			if (substr($m,0,3) == 'do_')
			{
				$arr[] = substr($m,3);
			}
		}

		$item_name = 'method';

		for ($i=0; $i < count($arr); $i++)
		{
			$arr[$item_name.'_'.$i] = $arr[$i];
			unset($arr[$i]);
		}

		$this->res['service'] = $this->serviceName;
		$this->res['methods'] = $arr;
		$this->send();

		exit;
	}

	/**
	 * Outputs XML describing required params, missing params, usage
	 *
	 */
	protected function showHelp()
	{
		foreach ((array) $this->required as $p)
		{
			$p = preg_replace("/\(\w+\)/",'',$p);
			$param[] = '{'.trim($p).'}';
		}

		if (is_array($param))
		{
			$param = implode('/',$param);
		}

		$this->res['status'] = 'error';

		$this->res['method'] = $this->action;
		
		if (isset($this->description) && !empty($this->description))
		{
			$this->res['description'] = $this->description;
		}

		if (is_array($this->required))
		{
			$this->res['params'] = implode(', ',$this->required);
		}

		if (is_array($this->missing))
		{
			$this->res['missing_params'] = implode(',',$this->missing);
			
			$error = '';
			foreach ($this->missing as $m)
			{
				$error .= "$m cannot be empty.\n";
			}
		}

		if (is_array($this->type_mismatch))
		{
			$this->res['type_mismatch'] = implode(',',$this->type_mismatch);
			$error = 'param type mismatch';
		}

		$this->res['usage'] = $_SERVER['HTTPS'] ? 'https://' : 'http://';

		$this->res['usage'] .= $_SERVER['HTTP_HOST']
			.substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],'/'))
			.'/'.$this->serviceName.'/'.$this->action.'/'.$param;

		$this->send('',$error);
		exit;
	}

	protected function msgDie($error=false)
	{
		$this->res['status'] = 'error';
		$this->res['message'] = $error;
		$this->res['error_flag'] = 1;
		$this->res['error_message'] = $error;
		$this->send('',$error);
		exit;
	}

	protected function send($items=false,$error_msg=false)
	{
		$root = 'response stat="ok"';

		if ($error_msg)
		{
			if (!is_array($this->res))
			{
				$this->res = array();
			}
			$root = 'response stat="fail"';
			$this->res = array_merge(array('error_flag'=>1,'error_message'=>$error_msg),$this->res);
			$items = false;
		}

		$format = 'xml';
		
		switch ($_GET['format'])
		{
			case 'json':
				header('Content-type: text/plain');
				echo json_encode($this->res);
				break;
			
			case 'xml':
			default:
				header('Content-type: text/xml');
				echo "<?xml version ='1.0' encoding ='ISO-8859-1' ?>\n";
				if (!isset($this->res) || empty($this->res))
				{
					$this->res = array('error_flag'=>1,'error_message'=>'no results found');
					$items = false;
				}

				echo $this->arrayToXml($this->res,$root,$items);
		}
		exit;
	}
	
	function arrayToXml($arr,$root_element='root',$item_name=false)
	{
		if ($item_name)
		{
			for ($i=0; $i < count($arr); $i++)
			{
				$arr[$item_name.'_'.$i] = $arr[$i];
				unset($arr[$i]);
			}
		}

		$str = '';
		
		if (isset($arr[0]))
		{
			$arr_mode = true;
		}
		else
		{
			if (!empty($root_element))
			{
				$str .= "<$root_element>\n";
			}
		}
		
		foreach ((array) $arr as $k=>$v)
		{
			if ($v == '') continue;

			// convert names like "item_1" to "item"
			$k2 = substr($k,strrpos($k,'_')+1);
			if (is_numeric($k2))
			{
				$k = substr($k,0,strrpos($k,'_'));
			}
				
			if (isset($arr_mode))
			{
				$k = $root_element;
			}
			
			if (!is_array($v))
			{
				$v = "<![CDATA[$v]]>";

				$str .= "<$k>$v</$k>\n";
			}
			else
			{
				$str .= $this->arrayToXml($v,$k);
			}
		}

		if ($pos = strpos($root_element,' '))
		{
			$root_element = substr($root_element,0,$pos);
		}
		
		if (!isset($arr_mode))
		{
			if (!empty($root_element))
			{
				$str .= "</$root_element>\n";
			}
		}
		
		return $str;
	}
}

?>