<?php

class Site
{
	// config properties
	static public $debug = false;
	static public $defaultPage = 'home.php';
	static public $collectionsMap = array(
		'site-root' => 'v-site-root'
		,'php-classes' => 'v-php-classes'
		,'php-config' => 'v-php-config'
	);

	static public $databaseHost;
	static public $databaseName;
	static public $databaseUsername;
	static public $databasePassword;
	

	// public properties
	//static public $ID;
	static public $Title;
	static public $rootPath;

	static public $webmasterEmail = 'errors@chrisrules.com';

	static public $requestURI;
	static public $pathStack;

	// protected properties
	static protected $_rootCollections;
	static protected $_config;
	static protected $_hostConfig;
	static protected $_parentHostConfig;

	
	static public function initialize()
	{
		// resolve details from host name
		
		// get site ID
/*
		if(empty(static::$ID))
		{
			if(!empty($_SERVER['SITE_ID']))
				static::$ID = $_SERVER['SITE_ID'];
			else
				throw new Exception('No Site ID detected');
		}
*/
		// get site root
		if(empty(static::$rootPath))
		{
			if(!empty($_SERVER['SITE_ROOT']))
				static::$rootPath = $_SERVER['SITE_ROOT'];
			else
				throw new Exception('No Site root detected');
		}

		// retrieve static configuration
		if(!(static::$_config = apc_fetch($_SERVER['HTTP_HOST'])) || ($_GET['_recache']=='fooforce'))
		{
			static::$_config = static::_compileConfiguration();
			apc_store($_SERVER['HTTP_HOST'], static::$_config);
		}

		// get host-specific config
		if(!static::$_hostConfig = static::$_config['hosts'][$_SERVER['HTTP_HOST']])
		{
			throw new Exception('Current host is unknown');
		}
		
		if(static::$_hostConfig['ParentHostname'])
		{
			if(!static::$_parentHostConfig = static::$_config['hosts'][static::$_hostConfig['ParentHostname']])
			{
				throw new Exception('Parent host is unknown');
			}
		}
		
		// get request URI
		if(empty(static::$requestURI))
			static::$requestURI = parse_url($_SERVER['REQUEST_URI']);
			
		// get path stack
		static::$pathStack = static::splitPath(static::$requestURI['path']);
		
		// register class loader
		spl_autoload_register('Site::loadClass');
				
	}
	
	static protected function _compileConfiguration()
	{
		$config = array();
		
		$config['hosts'] = DB::table('Hostname', 'SELECT s.*, h.Hostname, parent_h.Hostname AS ParentHostname FROM _e_hosts h LEFT JOIN _e_sites s ON(s.ID=h.SiteID) LEFT JOIN _e_hosts parent_h ON(parent_h.SiteID=s.ParentID)');
		
		return $config;
	}
	
	static public function handleRequest()
	{
		// TODO: try global handle lookup?

		// resolve URL in root
		$resolvedNode = false;
		$rootNode = static::getRootCollection('site-root');

		// handle root request
		if(empty(static::$pathStack[0]))
		{
			if(static::$defaultPage)
				$resolvedNode = $rootNode->getChild(static::$defaultPage);
		}
		elseif(static::$pathStack[0] == 'emergence')
		{
			array_shift(static::$pathStack);
			return Emergence::handleRequest();
		}
		else
		{
			$resolvedNode = $rootNode;
			while($handle = array_shift(static::$pathStack))
			{
				if(
					($childNode = $resolvedNode->getChild($handle))
					|| ($childNode = $resolvedNode->getChild($handle.'.php'))
				)
					$resolvedNode = $childNode;
				else
					break;
			}
			
			// no match found
			if($resolvedNode == $rootNode)
			{
				$resolvedNode = false;
			}
		}
		
		
		if(!$resolvedNode)
		{
			static::respondNotFound();
		}
		elseif($resolvedNode->MIMEType == 'application/php')
		{
			require($resolvedNode->RealPath);
			exit();
		}
		elseif(!is_callable(array($resolvedNode, 'outputAsResponse')))
		{
			throw new Exception('Node does not support rendering');
		}
		else
		{
			$resolvedNode->outputAsResponse();
		}
	}
	
	static public function resolveFile($path, $checkParent = true)
	{
		if(!is_array($path))
			$path = static::splitPath($path);

		$collectionHandle = array_shift($path);

		// get collection
		if(!$collectionHandle || !$collection = static::getRootCollection($collectionHandle))
		{
			throw new Exception('Could not resolve root collection: '.$collectionHandle);
		}

		// get file
		$node = $collection->resolvePath($path);

		// try to get from parent
		if(!$node && $checkParent)
		{
			$node = Emergence::resolveFileFromParent($collectionHandle, $path);
		}

		return $node;
	}
	
	
	static public function loadClass($className)
	{		
		// try to load class
		$classNode = static::resolveFile("php-classes/$className.class.php");
		
		if(!$classNode)
		{
			throw new Exception("Unable to load class '$className'");
		}
		
		readfile($classNode->RealPath);exit();

		if(!$classNode)
		{
			throw new Exception('Unable to find class "'.$className.'"');
		}
		elseif(!$classNode->MIMEType == 'application/php')
		{
			throw new Exception('Class file for "'.$className.'" is not application/php');
		}
		
		require($classNode->RealPath);
		

		// try to load config
		$configNode = static::getRootCollection('php-config')->getChild($className.'.config.php');
		
		if($configNode)
		{
			if(!$configNode->MIMEType == 'application/php')
			{
				throw new Exception('Config file for "'.$className.'" is not application/php');
			}
			
			require($configNode->RealPath);
		}
		
		//Debug::dump($classNode);
	}
	
	static public function respondNotFound($message = 'Page not found')
	{
		header('HTTP/1.0 404 Not Found');
		die($message);
	}
	
	static public function getRootCollection($handle)
	{
		if(!empty(static::$_rootCollections[$handle]))
			return static::$_rootCollections[$handle];
	
		if(!array_key_exists($handle, static::$collectionsMap))
			throw new Exception('Unable to find root collection: ' . $handle);
			
		return static::$_rootCollections[$handle] = SiteCollection::getOrCreateRootCollection(static::$collectionsMap[$handle]);
	}
	
	static public function getConfig($key = false)
	{
		return $key ? static::$_config[$key] : static::$_config;
	}
	
	static public function getHostConfig($key = false)
	{
		return $key ? static::$_hostConfig[$key] : static::$_hostConfig;
	}
	
	static public function getParentHostConfig($key = false)
	{
		return $key ? static::$_parentHostConfig[$key] : static::$_parentHostConfig;
	}
	
	static public function getSiteID()
	{
		return 1;//return static::getHostConfig('ID');
	}
	
	static public function splitPath($path)
	{
		return explode('/', ltrim($path, '/'));
	}
	
}
