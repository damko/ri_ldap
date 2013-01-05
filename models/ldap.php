<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/**
 * The LDAP object interacts directly with a LDAP server and performs on it a full CRUD. 
 * It connects to a master LDAP server to create, update and delete and to a slave LDAP server to read and search.
 * 
 * Every method which interacts with LDAP returns true or false as an exit status, while the LDAP errors or the LDAP results 
 * are stored in Ldap->result.
 * Ldap->result is a LDAP_Return_Object which is composed by two objects: LDAP_Error_Object and LDAP_Data_Object.
 * 
 * It's extented and loaded by the Ri_Ldap object which acts like an interface for the LDAP Object and adds precious information
 * for the REST protocol.
 * 
 * @author 		Damiano Venturin
 * @copyright 	2V S.r.l.
 * @license		GPL
 * @link		http://www.contact-engine.info
 * @since		Sep 2, 2011
 * 
 * @todo		Implement fail over for masters and slave server. Implement a loadbalancing system for slaves.
 */
class Ldap extends CI_Model {
	
	protected $connected;
	public $connection = NULL;	//the connection resource
	public $data;	//this is where ldap results are stored until the end of the processes and then they are pushed into $this->result by the LDAP_Return_Object->storeData(); 
	protected $debug = false;
	public $dn;
	public $result;
	protected $service_unavailable = false;
	
	/**
	 * Constructs the object and sets another PHP Error Handler 
	 * 
	 * @access		public
	 * @param		none
	 * @var			
	 * @return		nothing
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		
	 */
	public function __construct() {
		
		parent::__construct();
		
		set_error_handler(array(&$this, 'LdapErrorHandler'));
		
		$this->reset_result();
		//$this->data = new Ldap_Data_Object();
		
		log_message('debug', 'Ldap class has been loaded');
		
	}
	
	protected function reset_result() {
		$this->load->model('ldap_return_object');
		$this->load->model('ldap_data_object');
		
		//this is what will be returned beside the exit status
		$this->result = new Ldap_Return_Object();
		$this->data = new Ldap_Data_Object();
	}

	/**
	* Destroies the object and restores the previous PHP Error Handler
	*
	* @access		public
	* @param		none
	* @var
	* @return		nothing
	* @example
	* @see
	*
	* @author 		Damiano Venturin
	* @copyright 	2V S.r.l.
	* @license		GPL
	* @link		http://www.contact-engine.info
	* @since		Feb 24, 2012
	*
	* @todo
	*/	
	public function __destruct()
	{
		restore_error_handler();
	}
	
	/**
	 * Connects to a given ldap server
	 * 	useful info:
	 *	openldap error table:http://www.zytrax.com/books/ldap/ch12/
	 *	about referral: http://www.zytrax.com/books/ldap/ch11/referrals.html - http://www.zytrax.com/books/ldap/ch7/referrals.html 
	 * 
	 * @access		public
	 * @param		$ldapurl	string	The LDAP connection string like "ldap://hostname:port"
	 * @param		$ldapdn		string	The DN (distinguished name) for the LDAP user like "cn=damin,dc=example,dc=com". Used for LDAP binding.
	 * @param		$ldappw		string	The password for the specified DN. Used for LDAP binding.
	 * @param		$version	integer	The LDAP version. Default "3".
	 * @var			
	 * @return		boolean		True if it can connect and bind otherwise false.
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		
	 */
	public function connect($ldapurl,$ldapdn,$ldappw,$version = '3') {
		
		// Connecting to LDAP
		$this->connection = ldap_connect($ldapurl);
		if(!is_resource($this->connection))
		{
			$this->connected = false;
			$this->report('connection_false',$ldapurl);
			return false;
		} 

		// Ldap_connect doesn't work well. It fails ONLY if the protocol is wrong, let' say lda:// rather than ldap:// otherwise it's always true.
		// So to know if the ldap server really works I need to perform a bind request and then parse the returned error
		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $version);
		ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 1);

		// This is left commented on purpose, just in case someone needs to use the referal to connect to another master server.
		// Remember that, for slaves LDAP server the referral points to the master server
		//if($master)
		//{
		//	//rebind is a callback function to be called in case the action performed over the connection can't be executed
		//  //this is used to switch from the one master server to another master server
		//	$rebind	= call_user_func(array(&$this, 'rebind'));
		//	ldap_set_rebind_proc($connection, "$rebind");
		//}

		// binding to the LDAP server
		$ldapbind = ldap_bind($this->connection, $ldapdn, $ldappw);
		if($ldapbind)
		{
			$this->connected = true;
			return true;
		} else {
			$this->connected = false;
			$this->report('connection_false',$ldapurl);
			return false;
		}
	}	
	
	
	/**
	 * Connects to another LDAP server as specified in the referral if the given server can not perform the request.
	 * THIS FUNCTION IS INTENTIONALLY COMMENTED and NOT USED because there is a big deal with referral. If the server
	 * is down, there is no referral so it's much better to try to connect to another server specified in the config
	 * file. So this method is here just for documentation purpose in case of need. 
	 * 
	 * @access		private
	 * @param		
	 * @var			
	 * @return		
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		Needs to be evaluated and, maybe, implemented		
	 */
	private function rebind($connection=null,$referral=null) {
//		//I leave the 2 parameters optionals to avoid php errors but they are both mandatory
//		if(is_null($referral))
//		{
//			if($this->debug) $this->errorLog('Il server ldap master non ha fornito un referral valido: potrebbe essere mal configurato. Contatta un amministratore.');
//			return ;
//		}
//
//
//		//if this function is called means that the binding with the slave failed
//		 if ($this->debug)
//		 {
//		 $this->add2messagesStack('Bind failed while connecting to the slave server at '.$this->config->item('url','ldapSlave'), 'warning');
//		 log_message('debug', 'Bind failed while connecting to the slave server at '.$this->config->item('url','ldapSlave'));
//		 if(!empty($referral))
//		 {
//		 	$this->add2messagesStack('Given referral is : '.$referral, 'warning');
//		 } else {
//		 	$this->add2messagesStack('Referral is empty! Probably you have a failure on the slave ldap server!', 'error');
//		 }
//		 }
//		  
//		 // LDAP variables: I'm just ignoring the referral sent by the ldap server
//		 $ldapurl = $this->config->item('url','ldapMaster');
//		 $ldapdn = $this->config->item('binddn','ldapMaster');
//		 $ldappw = $this->config->item('bindpw','ldapMaster');
//
//		 // Connecting to LDAP
//		 $this->ldapConnection = ldap_connect($ldapurl) or die("Cannot connect to $ldapurl");
//		 	
//		 ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
//		 ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, 1);
//		 $ldapbind = ldap_bind($this->ldapConnection, $ldapdn, $ldappw);
//		 if ($ldapbind and $this->debug) $this->add2messagesStack('Bind ok while connecting to the master server at '.$ldapurl, 'notification');
//		 if(!$ldapbind)
//		 {
//			 $this->add2messagesStack('Bind failed while connecting to the master server at '.$ldapurl, 'error');
//		 }
	}	
	
	/**
	 * Unbinds the current connection to LDAP
	 * 
	 * @access		public
	 * @param		none
	 * @var			
	 * @return		nothing
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		
	 */
	public function disconnect() {
		if(is_resource($this->connection)) ldap_unbind($this->connection);
	}
	
	
	/**
	 * Creates a LDAP entry
	 * 
	 * @access		public
	 * @param		$entry	array	Mandatory. The array containing the data for the entry.
	 * @param		$dn		string	The DN of the new entry. If empty $this->dn will be used instead.
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 25, 2012
	 * 
	 * @todo		
	 */
	public function create($entry, $dn = null) {
		
		if(!empty($dn) and !is_array($dn)) $this->dn = $dn;
		
		$params = get_defined_vars();
		$params['command'] = 'ldap_add';
				
		return $this->run($params) ? true : false;	
	}
	
	
	/**
	 * Deletes a LDAP entry
	 * 
	 * @access		public
	 * @param		$dn		string	The DN of the new entry. If empty $this->dn will be used instead.
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @since		Mar 4, 2012	
	 */
	public function delete($dn = null)
	{
		if(!empty($dn) and !is_array($dn)) $this->dn = $dn;

		$params = array('command' => 'ldap_delete');
		
		return $this->run($params) ? true : false;
	}
	
	
	/**
	 * Performs a LDAP search with "server side pagination". "Server side pagination" means that only a specified subset of the results is returned. 
	 * It stores the LDAP results in LDAP->data adding useful information about the result itself so that it's easy to make pagination on the client side. 
	 * 
	 * @access		public
	 * @param		$baseDN			string		Search param. Mandatory. The LDAP baseDN like "ou=sales,dc=example,dc=com".
	 * @param		$filter			string		Search param. Mandatory. The LDAP filter string like "(&(givenName=John)(l=Dallas))".
	 * @param		$attributes		array		Search param. A simple array containing the LDAP attributes to get in return like "array('uid','cn','givenName');". If it's not set returns all the attributes for the entry.
	 * @param		$attributesOnly	integer		Search param. It can be '0' or '1'. If it's set to '1' the search returns only the attributes without the values.
	 * @param		$sizeLimit		integer		Search param. It limits the max amount of items to get from LDAP. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$timeLimit		integer		Search param. It limits the max amount of time to perform the LDAP search query. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$deref			integer		Search param. It specifies how aliases should be handled during the search. http://www.php.net/ldap_list
	 * @param		$sort_by		array		Server side pagination parameter. A simple array containing the LDAP attributes order like "array('sn','givenName');". This will make results ordered by lastname and firstname.
	 * @param		$flow_order		string		Server side pagination parameter. It could be "asc" or "desc". "asc" -> from A to Z or from smaller numbers to bigger. Viceversa for "desc".
	 * @param		$wanted_page	integer		Server side pagination parameter. The page number to send back. Let's say that a search with 5 items per pages makes a 13 pages result. With "$wanted_page = 3" I get back items from 14 to 19.
	 * @param		$items_page		integer		Server side pagination parameter. The number of items for page.
	 * @var			
	 * @return		
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @since		Feb 24, 2012
	 * 
	 * @todo	It's possible to perform a search on multiple DNs. http://www.php.net/manual/en/function.ldap-search.php#94554 This might be useful if I want to perform a search on both people and organizations in one shot	
	 */
	public function search($baseDN, $filter, $attributes = null, $attributesOnly = 0, $sizeLimit = null, $timeLimit = null, $deref = null, $sort_by = null, $flow_order = null, $wanted_page = null, $items_page = null) {
	
		//I avoided to use ldap_search because when the results number is > 1 the "dn" is always returned irrespectively of which attributes types are requested
		//and this gives problems when sorting (the first item goes somewhere else in the array and it's hard to remove it)
		$params = get_defined_vars();
		$params['command'] = 'ldap_list';
		$resource = $this->run($params);
		if($resource === false) return false;
		
		//counting results number
		$params = array(
						'command' => 'ldap_count_entries',
						'result' => $resource,
						);		
		$result = $this->run($params);
		if($result === false) {
			return false;
		} else {
			$this->data->results_number = $result;
		}
		
		//retrieving entries: sorting and paginating results if necessary
		return $this->sort_paginate($resource, $sort_by, $flow_order, $wanted_page, $items_page) ? true : false;
	}
	
	/**
	 * The method read() is a sort of interface for the search() method and it's meant to return all the attributes of the current ldap object.
	 *
	 * @access		public
	 * @param		$dn				string	The DN of the new entry. If empty $this->dn will be used instead.
	 * @param		$sizeLimit		integer		Search param. It limits the max amount of items to get from LDAP. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$timeLimit		integer		Search param. It limits the max amount of time to perform the LDAP search query. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$deref			integer		Search param. It specifies how aliases should be handled during the search. http://www.php.net/ldap_list
	 *
	 * @author 		Damiano Venturin
	 * @since		Mar 4, 2012
	 */
	public function read($dn = null, $sizeLimit = null, $timeLimit = null, $defer = null) {
		if(!empty($dn) and !is_array($dn)) $this->dn = $dn;
	
		$pieces = preg_split('/,/', $this->dn);
	
		$filter = '('.$pieces[0].')';
	
		unset($pieces[0]);
		$baseDN = implode(',', $pieces);
	
		$attributes = array();
	
		return $this->search($baseDN, $filter, $attributes, 0, $sizeLimit, $timeLimit, $defer);
	}	
	
	/**
	 * Updates a LDAP entry
	 * 
	 * @access		public
	 * @param		$entry	array	Mandatory. The array containing the data for the entry.
	 * @param		$dn		string	The DN of the new entry. If empty $this->dn will be used instead.
	 * @return		boolean
	 * 
	 * @author 		Damiano Venturin
	 * @since		Mar 4, 2012
	 */
	public function update($entry, $dn = null, $delete = false) {
		
		if(!empty($dn) and !is_array($dn)) $this->dn = $dn;
		
		if($delete){
			$params = array(
					'command' => 'ldap_mod_del',
					'entry' => $entry,
			);
				
		} else {
			$params = array(
								'command' => 'ldap_modify',
								'entry' => $entry,
			);
		}
				
		return $this->run($params) ? true : false;
	}

	/**
	 * Performs the validation of the LDAP parameters and throws errors if necessary.
	 * 
	 * @access		private
	 * @param		$params		array	Mandatory. It contains the parameters for the LDAP function.
	 * @return		boolean		True if it's validated.
	 * 
	 * @author 		Damiano Venturin
	 * @since		Feb 24, 2012
	 */
	private function preRunValidation($command,array $params)
	{
		if(isset($this->result->errors))
		{
			if (count($this->result->errors) > 0) 
				return false;
		}
		if($this->service_unavailable) return false;
		
		extract($params);
		
		if(!$this->connection) {
			$this->report('connection_false', 'unknown');
			return false;
		}
		
		if(!is_resource($this->connection)) {
			$this->report('connection','');
			return false;
		}
		
		if($command == 'ldap_delete' || $command == 'ldap_modify' || $command == 'ldap_add' ) {
			if(empty($this->dn) or is_array($this->dn)) {
				$this->report('dn', null);
				return false;
			}
		}

		if($command == 'ldap_modify' || $command == 'ldap_add' ) {
			if(!is_array($entry) || count($entry) == 0) {
				$this->report('entry', null);
				return false;
			}
		}

		if($command == 'ldap_list') {
			if(!$baseDN) {  //mandatory for ldap_list
				$this->report('baseDN_false','');
				return false;
			}
			
			if(!$filter) { //mandatory for ldap_list
				$this->report('filter_false','');
				return false;
			}

			if(is_array($filter)) { //mandatory for ldap_list
				$this->report('trigger','A filter can not be an array.','415');
				return false;
			}

			if(!isset($attributes) || !is_array($attributes)) {
				$this->report('trigger','The parameter "attributes" must be an array.','415');
				return false;
			}

			if(isset($attributesOnly) && ($attributesOnly != 0 && $attributesOnly != 1)) {
				$this->report('trigger','The parameter "attributesOnly" must be 0 or 1.','415');
				return false;
			}			
		}
		
		if($command == 'ldap_count_entries') {
			if(!is_resource($result)){
				$this->report('trigger','The result_identifier passed to ldap_count_entries is not a valid resource.');
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Executes a LDAP command and throws errors if needed.
	 * 
	 * @access		private
	 * @param		$params		array	Mandatory. It contains the parameters for the LDAP function.			
	 * @return		$result		It can be the LDAP result or false if something went wrong.
	 * 
	 * @author 		Damiano Venturin
	 * @since		Feb 24, 2012	
	 */
	private function run(array $params)
	{
		//just to avoid notifications
		$attributes = null; 
		$attributesOnly = null; 
		$sizeLimit = null; 
		$timeLimit = null; 
		$deref = null;
		
		extract($params);
		
		//validation
		
		if(!$this->preRunValidation($command, $params)) return false;
		
	
		switch ($command) {
			
			case 'ldap_add':
				if(! $result = ldap_add($this->connection, $this->dn, $entry)) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
					
					//Already exists message
					if($ldap_error['ldap_errno'] == '68') {
						$message .= ' or your DN is wrong. It should be the DN of the new entry and not an already existent one.';
						$http_status_code = '415';
					}
					//Object Class Violation
					if($ldap_error['ldap_errno'] == '65') {
						$message .= '. Probably a mandatory field for the entry is missing or malformed.';
						$http_status_code = '415';
					}										
				}
			break;
	
			case 'ldap_modify':
				//$a = $entry;
				//$entry['oAdminRDN'] = array();
				if(! $result = ldap_modify($this->connection, $this->dn, $entry)) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
				}
			break;
							
			case 'ldap_delete':
				if(! $result = ldap_delete($this->connection, $this->dn)) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
					if($ldap_error['ldap_errno'] == '32') {
						$message .= '. The entry you want to delete does not exist.';
						$http_status_code = '415';
					}					
				}
			break;

			case 'ldap_mod_del':
				if(! $result = ldap_mod_del($this->connection, $this->dn, $entry)) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
				}
			break;			
			
			case 'ldap_list'; 
				//adjusting optional search parameters
				if(is_null($sizeLimit)) $sizeLimit = $this->conf['sizeLimit'];
				if(is_null($timeLimit)) $timeLimit = $this->conf['timeLimit'];
				if(is_null($deref)) $deref = $this->conf['defer'];					
					
				if(! $result = ldap_list($this->connection, $baseDN, $filter, $attributes, $attributesOnly, $sizeLimit, $timeLimit, $deref)) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
					if($ldap_error['ldap_errno'] == '32') {
						$message .= '. Please check your baseDN.';
						$http_status_code = '415';
					}
					if($ldap_error['ldap_errno'] == '-7') { 
						$message .= '. Please check your filter.';
						$http_status_code = '415';
					}
				}
			break;
			
			case 'ldap_count_entries':
				$result = ldap_count_entries($this->connection, $result);
				if($result === false) {
					$ldap_error = $this->getLdapError($command);
					$message = $ldap_error['message'];
				}
			break;
			
			default:
				$this->report('trigger','Ldap command '.$command.' not found.','500');
				return false;
			break;
		}
		
		//throws the Exception
		if(isset($message)) {
			if($ldap_error['ldap_errno'] == '21') {
				$message .= '. Ldap syntax error.';
				$http_status_code = '500';
			}						
			if(!isset($http_status_code)) $http_status_code = '500';
			$this->report('trigger', $message, $http_status_code);
		}
		
		return $result;
	}	
	
	/**
	 * Analyses the last LDAP error and retrieves the LDAP error number and the LDAP error message.
	 * 
	 * @access		private
	 * @param		$command	string	The executed LDAP command. It's used to improve the returned error message
	 * @return		$result		array	The array containing the errors. $result['ldap_errno'] contains the LDAP error message. $result['ldap_errstr'] contains the LDAP error message. $result['message'] contains the improved error message that will be really used. 
	 * 
	 * @author 		Damiano Venturin
	 * @since		Feb 24, 2012
	 */
	private function getLdapError($command = null){
		$result = array();
		$result['ldap_errno'] = ldap_errno($this->connection);
		$result['ldap_errstr'] = ldap_error($this->connection);
		
		if(!is_null($command) && !is_array($command)) $result['message'] = 'LDAP command: '.$command.' - ';
		
		$result['message'] .= 'LDAP error: code:'.$result['ldap_errno'].' - '.$result['ldap_errstr'];
		return $result;
	}
	

	/**
	 * Sorts a LDAP search in ascending and descending order. It stores the LDAP results in LDAP->data.
	 * 
	 * @access		private
	 * @param 		$resource 		resource	The LDAP resource got from ldap_list()
	 * @param 		$sort_by		array		A simple array containing the LDAP attributes order like "array('sn','givenName');". This will make results ordered by lastname and firstname.
	 * @param		$flow_order		string		It could be "asc" or "desc". "asc" -> from A to Z or from smaller numbers to bigger. Viceversa for "desc".
	 * @param		$wanted_page	integer		The page number to send back. Let's say that a search with 5 items per pages makes a 13 pages result. With "$wanted_page = 3" I get back items from 14 to 19.
	 * @param		$items_page		integer		The number of items for page.	
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		
	 */
	private function sort_paginate($resource, array $sort_by = null, $flow_order = "asc", $wanted_page = 0, $items_page = 0 )
	{				
		if($resource == 0) {
			$this->data->content = array();
			$this->data->results_got_number = 0;
			$this->data->results_pages = '0';
			$this->data->results_page = '0';
			return true;
		}
		
		//validation		
		if(!is_resource($resource)) {
			$this->report('invalid_resource', '');
			return false;
		}
		
		if(is_null($sort_by)) $sort_by = array();

		if(!is_array($sort_by))
		{
			$this->report('trigger', __FUNCTION__.': sort_by should be an array.','415');
			return false;
		}
		
		if(is_array($flow_order))
		{
			$this->report('trigger', __FUNCTION__.': The flow_order should be a string with one of these values: "asc" or "desc".','415');
			return false;
		}
		
		if(is_null($flow_order)) $flow_order = 'asc';
		$available_orders = array('asc','desc');
		if(!in_array($flow_order, $available_orders)) {
			$this->report('trigger', __FUNCTION__.': The flow_order should be either "asc" or "desc".','415');
			return false;
		}
		
		if(is_null($wanted_page)) $wanted_page = '0'; //means "give me all the content"
		if(!is_numeric($wanted_page))
		{
			$this->report('trigger', __FUNCTION__.': The wanted page should be an integer.','415');
			return false;
		}

		if(is_null($items_page)) $items_page = '0'; //means "give me all the content"
		if(!is_numeric($items_page))
		{
			$this->report('trigger', __FUNCTION__.': The number of items per page should be an integer.','415');
			return false;
		}
				
		//get the range of item equivalent to the searched page	
		if ($items_page == '0' )
		{
			# fetch all in one page
			$item_start = 0;
			$item_end = $this->data->results_number - 1;
		}
		else
		{
			# calculate range of page
			$item_start = $items_page * $wanted_page;
			$item_end = $item_start + $items_page - 1;
			if ( $flow_order === "desc" )
			{
				# revert range
				$item_start = $this->data->results_number - 1 - $item_end;
				$item_end = $item_start + $items_page - 1;
			}
		}
		
		# sort ldap entries
		foreach ($sort_by as $field) {
			ldap_sort( $this->connection, $resource, $field );
		}
		
		//get the selected range of items
		$content = array();
		for (
	        	$current_item = 0, $current_entry = ldap_first_entry( $this->connection, $resource );
				$current_item <= $item_end && is_resource( $current_entry );
				$current_item++, $current_entry = ldap_next_entry( $this->connection, $current_entry )
			) 
		{
			if ( $current_item >= $item_start )
			{
				array_push( $content, ldap_get_attributes( $this->connection, $current_entry ) );
			}
		}
		
		# if order is desc revert page's entries
		if($flow_order === "desc") $content = array_reverse( $content );
		
		$this->data->content = $content;
		
		//adding RESTinfo
		$this->data->results_got_number = count($content);
		
		if($items_page == '0') {
			$this->data->results_pages = '0';
		} else {
			$this->data->results_pages =  ceil( $this->data->results_number / $items_page );
		}
				
		if($this->data->results_got_number == $this->data->results_number) 
		{
			$this->data->results_page = '0';
		} else {
			$this->data->results_page = $wanted_page;
		}
				
		return true;
	}	
	
	/**
	 * This functions triggers error messages. It has some typical error message cases and adds the http_status_code for REST.
	 * 
	 * @access		protected
	 * @param		$type				string		The error case.
	 * @param		$message			string		The error message.
	 * @param		$http_status_code	integer		The http_status_code used by REST. http://restpatterns.org/HTTP_Status_Codes
	 * @var			
	 * @return		
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 * 
	 * @todo		
	 */
 	protected function report($type, $message, $http_status_code = null)
	{
		switch ($type) {
			
			case 'baseDN_false':
				return $this->report('trigger','No baseDN was specified.','415');
			break;

			case 'configuration_empty':
				return $this->report('trigger','The configuration item "'.$message.'" is not set. Please check the ri_ldap.php configuration file.','500');
			break;			
			
			case 'connection':
				return $this->report('trigger','Ldap connection error: '.$message.'.','500');
			break;
							
			case 'connection_false':
				return $this->report('trigger','Ldap connection error: no valid connection established with this URL: '.$message.' Check the ri_ldap.php configuration file.','400');
			break;
							
			case 'dn':
				return $this->report('trigger','No valid uid or dn was provided.','415');
			break;			
			
			case 'entry':
				return $this->report('trigger','The attributes provided are not validated.','415');
			break;

			case 'filter_false':
				return $this->report('trigger','No filter was specified.','415');
			break;

			case 'invalid_resource':
				return $this->report('trigger','The LDAP resource is not valid.','500');
			break;
							
			case 'search_ldap_query':
				return $this->report('trigger','Error in search LDAP query.','500');
			break;
			
			case 'service_unavailable':
				return $this->report('trigger','Service Unavailable', '503');
			break;

			default:
			case 'trigger':
  				try {
					if(true)
					{
						if(is_null($http_status_code)) $http_status_code = '500';
						throw new OutOfRangeException($message, $http_status_code);
					}
				} catch (Exception $e) {
					return $this->LdapErrorHandler(8,$e->getMessage(),$e->getFile(),$e->getLine(), null, $http_status_code);
				} 
 			break;
		}
	} 
	
	/**
	 * PHP Error Handler replacement (only for the objects Ldap and Ri_Ldap)
	 *
	 * PHP ERROR TYPES:
	 * 2 		E_WARNING 				Non-fatal run-time errors. Execution of the script is not halted
	 * 8 		E_NOTICE 				Run-time notices. The script found something that might be an error, but could also happen when running a script normally
	 * 256 		E_USER_ERROR 			Fatal user-generated error. This is like an E_ERROR set by the programmer using the PHP function trigger_error()
	 * 512 		E_USER_WARNING 			Non-fatal user-generated warning. This is like an E_WARNING set by the programmer using the PHP function trigger_error()
	 * 1024 	E_USER_NOTICE 			User-generated notice. This is like an E_NOTICE set by the programmer using the PHP function trigger_error()
	 * 4096 	E_RECOVERABLE_ERROR 	Catchable fatal error. This is like an E_ERROR but can be caught by a user defined handle (see also set_error_handler())
	 * 8191 	E_ALL 					All errors and warnings, except level E_STRICT (E_STRICT will be part of E_ALL as of PHP 6.0)
	 * 
	 * 
	 * @access		public
	 * @param		$errno			integer		The PHP error code number
	 * @param		$errstr			string		The error message
	 * @param		$errfile		string		The path of the file generating the error
	 * @param		$errline 		string		Optional. The line of code generating the error
	 * @param		$errcontext 	string		Optional. The attached variables (sort of backtrace)
	 * @var			
	 * @return		false			boolean		Returns always false because an error exception was trown and catched	
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.squadrainformatica.com/en/development#mcbsb  MCB-SB official page
	 * @since		Feb 16, 2012
	 * 
	 * @todo		
	 */
	public function LdapErrorHandler($errno, $errstr, $errfile, $errline, $errcontext = null, $http_status_code = null)
	{	
		$this->result->addError($errno, $errstr, $errfile, $errline, $http_status_code);
		log_message('DEBUG','!#### '.$errstr.' ##### '.$errfile.' '.$errline);
		
		return false;
	}	

}


/* End of ldap.php */