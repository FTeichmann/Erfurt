<?php
class Erfurt_Auth_Adapter_RDF implements Zend_Auth_Adapter_Interface 
{ 
	
	/**
     * Digest authentication user
     *
     * @var string
     */
    protected $_username;

    /**
     * Password for the user of the realm
     *
     * @var string
     */
    protected $_password;
    
    /**
     * ac model
     *
     * @var object
     */
    protected $_acModel;
    
    private $_users = array();
    
    private $userFetched = false;
    
    private $_anonymousUserUri = 'http://ns.ontowiki.net/SysOnt/Anonymous';
    
    private $_userClass = 'http://xmlns.com/foaf/0.1/Agent';
    private $_userNamePredicate = 'http://xmlns.com/foaf/0.1/nick';
    private $_userPasswordPredicate = 'http://ns.ontowiki.net/SysOnt/userPassword';
    private $_userMailPredicate = 'http://xmlns.com/foaf/0.1/mbox';
    private $_userDenyActionPredicate = 'http://ns.ontowiki.net/SysOnt/denyAccess';
    private $_userDenyLoginAction = 'http://ns.ontowiki.net/SysOnt/denyAccess';
    
    private $_dbUsername = '';
    private $_dbPassword = '';
    
	/**
	* Sets username and password for authentication
	*
	* @return void
	*/ 
	public function __construct($acModel, $username, $password) 
	{ 
		Zend_Log::log('Erfurt_Auth_Adapter_RDF::_constuctor()', Zend_Log::LEVEL_DEBUG, 'erfurt');
		$this->_acModel = $acModel;
		
		$this->_username = $username;
		$this->_password = $password;
		
		$this->_userClass = Zend_Registry::get('config')->ac->user->class;
		$this->_userNamePredicate = Zend_Registry::get('config')->ac->user->name;
		$this->_userPasswordPredicate = Zend_Registry::get('config')->ac->user->pass;
		$this->_userMailPredicate = Zend_Registry::get('config')->ac->user->mail;
		
		$this->_anonymousUserUri = Zend_Registry::get('config')->ac->user->anonymousUser;
		
		$this->_dbUsername = Zend_Registry::get('config')->database->params->username;
		$this->_dbPassword = Zend_Registry::get('config')->database->params->password;
		
		
	} 
	
	/**
	* Performs an authentication attempt
	*
	* @throws Zend_Auth_Adapter_Exception If authentication cannot be performed
	* @return Zend_Auth_Result
	*/ 
	public function authenticate() { 
		# returnset
		$result = array(
	           'isValid'  => false,
	           'identity' => array(
	               'username' => $this->_username,
	               'uri'	=>	'',
	               'dbuser'	=>	false,
	               'anonymous' => false
	               ),
	           'messages' => array()
	           );
	  if ($this->_username == 'Anonymous') {
	  	$result['isValid'] = true;
	  	$result['identity'] = $this->_getAnonymous();
	  }
	  # normal user
		else if (($userUri = $this->fetchData($this->_username, $this->_password)) === false) {
			$result['messages'][] = Zend_Registry::get('strings')->auth->login->msg->incorrect;
		} 
		# database user
		else if ($this->_username == $this->_dbUsername and $this->_password == $this->_dbPassword) {
			$result['isValid'] = true;
			$result['identity']['uri'] = $this->_dbUsername;
			$result['identity']['email'] = '';
			$result['identity']['dbuser'] = true;
		} 
		# valid user from sysont
		else {
			$result['isValid'] = true;
			$result['identity']['uri'] = $userUri;
			$result['identity']['email'] = $this->_users[$userUri]['userEmail'];
			
			# POWL HACK
			$_SESSION['PWL']['user']= $this->_username;
		}
		
		
		return new Zend_Auth_Result($result['isValid'], $result['identity'], $result['messages']);
	}
 
 	
	/**
 	* fetch the user data from rdf-store
 	*/
	function fetchUserData() {
		Zend_Log::log('Erfurt_Auth_Adapter_RDF::fetchUserData()', Zend_Log::LEVEL_DEBUG, 'erfurt');
   	$this->userFetched = true;
   	
		# no system-ontology
		if (empty($this->_acModel->modelURI)) {
			return false;
		}
       
		## direct user rights
		$sparqlQuery = 'select ?p ?o 
														where { 
														?s ?p ?o								
														?s rdf:type <'.$this->_userClass.'>.
														}';
			
		if (!$result = $this->_sparql($sparqlQuery)) {
			return false;
		}
      
		foreach($result as $e) {
			if ($e['?p']->getUri() == $this->_userNamePredicate)
				$this->_users[$e['?s']->getUri()]['userName'] = $e['?o']->getLocalName();
			else if ($e['?p']->getUri() == $this->_userPasswordPredicate)
				$this->_users[$e['?s']->getUri()]['userPassword'] = $e['?o']->getLabel();
			else if ($e['?p']->getUri() == $this->_userMailPredicate)
				$this->_users[$e['?s']->getUri()]['userEmail'] = $e['?o']->getLocalName();
			else if ($e['?p']->getUri() == $this->_userMailPredicate and $e['?o']->getUri() == $this->_userDenyLoginAction)
				$this->_users[$e['?s']->getUri()]['loginForbidden'] = true;
		}        
	}
    
	/**
	* Fetch data from rdf store
	*
	* @access public
	*/
	public function fetchData($username, $password, $isChallengeResponse=false) {
	  Zend_Log::log('Erfurt_Auth_Adapter_RDF::fetchData()', Zend_Log::LEVEL_DEBUG, 'erfurt');
		# no system-ontology
		if (empty($this->_acModel->modelURI)) {
			return false;
		}
       
		## direct user rights
		$sparqlQuery = 'select ?s ?p ?o 
														where { 
														?s ?p ?o.								
														?s rdf:type <'.$this->_userClass.'>.
														?s <'.$this->_userNamePredicate.'> "'.$username.'"
														}';
		
		if (!$result = $this->_sparql($sparqlQuery)) {
			return false;
		}
		
		
		$uri = '';
		foreach($result as $e) {
			## user check
			# wront username => not possible :)
			if ($e['?p']->getUri() == $this->_userNamePredicate and $e['?s']->getLocalName() != $username)
				return false;
			# wrong password
			if ($e['?p']->getUri() == $this->_userPasswordPredicate 
					and (!$this->verifyPassword($password, $e['?o']->getLabel(), 'sha1') 
	        		and !$this->verifyPassword($password, $e['?o']->getLabel(), '')))
				return false;
	        		
			# set params
			if ($uri == '')
				$uri = $e['?s']->getUri();
			if ($e['?p']->getUri() == $this->_userNamePredicate)
				$this->_users[$e['?s']->getUri()]['userName'] = $e['?s']->getLocalName();
			else if ($e['?p']->getUri() == $this->_userPasswordPredicate)
				$this->_users[$e['?s']->getUri()]['userPassword'] = $e['?o']->getLabel();
			else if ($e['?p']->getUri() == $this->_userMailPredicate)
				$this->_users[$e['?s']->getUri()]['userEmail'] = $e['?o']->getLocalName();	
			# user denied to login
			else if ($e['?p']->getUri() == $this->_userMailPredicate and $e['?o']->getUri() == $this->_userDenyLoginAction)
				return false;
		}
		
	  return  $uri;
	}
    
   
    
	/**
	 * Returns a list of users available within the container
	 *
	 * @return array
	 */
	public function listUsers() {
	    Zend_Log::log('Erfurt_Auth_Adapter_RDF::listUsers()', Zend_Log::LEVEL_DEBUG, 'erfurt');
	    
		if (!$this->userFetched) {
	    	$this->fetchUserData();
	    }
	    
	    $ret = array();
	    return $this->_users;
	}
    
	/**
	* Crypt and verfiy the entered password
	*
	* @param  string Entered password
	* @param  string Password from the data container (usually this password
	*                is already encrypted.
	* @param  string Type of algorithm with which the password from
	*                the container has been crypted. (md5, crypt etc.)
	*                Defaults to "md5".
	* @return bool   True, if the passwords match
	*/
	private function verifyPassword($password1, $password2, $cryptType = "md5") {
	   switch ($cryptType) {
	       case "crypt" :
	           return ((string)crypt($password1, $password2) === (string)$password2);
	           break;
	       case "none" :
	       case "" :
	           return ((string)$password1 === (string)$password2);
	           break;
	       case "md5" :
	            return ((string)md5($password1) === (string)$password2);
	            break;
	       case "sha1" :
	       	return ((string)sha1($password1) === (string)$password2);
	       	break;
	       default :
	            if (function_exists($cryptType)) {
	                return ((string)$cryptType($password1) === (string)$password2);
	            } elseif (method_exists($this,$cryptType)) { 
	                return ((string)$this->$cryptType($password1) === (string)$password2);
	            } else {
	                return false;
	            }
	            break;
	    }
	}
	
	/**
	 * parse sparql query 
	 */
	private function _sparql($sparqlQuery) {
		static $prefixed_query;
		
		# get all 
		if ($prefixed_query == '') { 
		$prefixed_query = '';
			foreach ($this->_acModel->getParsedNamespaces() as $uri => $prefix) {
				$prefixed_query .= 'PREFIX ' . $prefix . ': <' . $uri . '>' . PHP_EOL;
			}
		}
		# query model
		try {
			$result = $this->_acModel->sparqlQuery($prefixed_query.$sparqlQuery, false);
		
		} catch (SparqlParserException $e) {
			Zend_Log::log('Erfurt_Auth_Adapter_RDF::_sparql() - query contains the following error: '.$e->getMessage(), Zend_Log::LEVEL_DEBUG, 'Controller');
			return false;
		} catch (Exception $e) {
			Zend_Log::log('Erfurt_Auth_Adapter_RDF::_sparql() - There was a problem with your query, most likely due to a syntax error.: '.$e->getMessage(), Zend_Log::LEVEL_DEBUG, 'Controller');
			return false;
		}
		return $result;
	}
	
	
	/**
	 * delievers the anonymous user details 
	 */
	public function _getAnonymous() {	
		$user['userName'] = 'Anonymous';
		$user['uri'] = $this->_anonymousUserUri;
		$user['dbuser'] = false;
		$user['email'] = '';
		$user['anonymous'] = true;
		return $user;
	}

} 
?>