<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * The Ri_Ldap object acts like an interface between the LDAP object class and the Person, Organization, Location object classes defined in 
 * the spark module ri_contact_engine.
 * If you just want to connect to LDAP and to perform raw commands on the LDAP you should use the LDAP class instead of this one.
 * 
 * The configuration file for this class is config/ri_ldap.php (in the sparks folder)
 *
 * @var		$conf			protected array		Contains the configuration read from the config file
 * @var 	$servers 		protected array		Contains the list of servers to which connections were established
 * @var		$WrConnection	protected resource	Ldap connection resource pointing to the current master server
 * @var		$RoConnection	protected resource	Ldap connection resource pointing to the current slave server
 * 
 * @author 		Damiano Venturin
 * @copyright 	2V S.r.l.
 * @license		GPL
 * @link		http://www.squadrainformatica.com/en/development#mcbsb  MCB-SB official page
 * @since		Sep 02, 2011
 * 
 * @todo		
 */ 
class Ri_Ldap extends Ldap {
	protected $conf = array();
	protected $servers = array();  //contains the servers configuration 
	protected $WrConnection;
	protected $RoConnection;
	
	/**
	 * The constructor method loads the LDAP class constructor, validates the ri_ldap.php configuration file and sets $ldap->debug and $ldap->service_unavailable
	 * accordingly to the configuration file.
	 * 
	 * @access		public
	 * @param		none
	 * @return		none
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
	public function __construct() {
		parent::__construct();
		
		$this->conf = $this->config->item('ldap');
		
		//validation for the config file
		$conf_entries = array('sizeLimit','timeLimit','defer','debug','service_unavailable');
		foreach ($conf_entries as $conf_entry) {
			if(is_null($this->conf[$conf_entry])) {
				return $this->report('configuration_empty',$conf_entry);
			}
		}		
		
		$this->debug = $this->conf['debug'];
		
		if( $this->service_unavailable = $this->conf['service_unavailable'] ) $this->report('service_unavailable', null);		
		
		log_message('debug', 'ri_ldap class has been loaded');
	}
	
	/**
	 * The destructor method destroyes the Ri_Ldap object and all the LDAP connections. 
	 * 
	 * @access		public
	 * @param		
	 * @var			
	 * @return		
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
	public function __destruct() {
		foreach ($this->servers as $key => $item) {
			foreach ($item as $key => $server) {
				if(!empty($server['connection'])) 
				{
					$this->connection = $server['connection'];
					$this->disconnect();	
				}
			}
		}
		parent::__destruct();
	}
	
	public function getServers()
	{
		return $this->servers;
	}
	
	/**
	 * Starts the connection to the LDAP server (at least one master and one slave) 
	 * 
	 * @access		public
	 * @param		none
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.squadrainformatica.com/en/development#mcbsb  MCB-SB official page
	 * @since		Sep 02, 2011
	 * 
	 * @todo		
	 */
	public function initialize()
	{
		//establish the connection to one of the Master servers
		log_message('debug', 'Establishing master LDAP servers connections');
		$this->establishLdapConnection('ldapMaster',true);
		
		
		log_message('debug', 'Establishing slave LDAP servers connections');
		$this->establishLdapConnection('ldapSlave',false);
		
		if($this->checkNeededConnections())
		{
			$this->getActiveConnections();
		} else {
			return $this->restReturn(false);
		}
		return $this->restReturn(true);		
	}

	/**
	 * Establishes a connection to the given LDAP server. The information about the server are retrieved from the configuration file.
	 * 
	 * @access		private
	 * @param 		$configItem		text 		Mandatory. Name of the configuration item. Possible values: ldapMaster or ldapSlave
	 * @param 		$master			boolean		Mandatory. Specifies if the connection to establish is meant to be a connection to a master LDAP server or a slave
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 * 
	 * @todo		
	 */
	private function establishLdapConnection($configItem, $master){
		//check configuration
		if(!$this->config->item($configItem)) return $this->report('configuration_empty',$configItem); //die('No configuration found with item "'.$configItem.'". Check your configuration file.');

		//test all the given connections (specified in the config file)
		foreach ($this->config->item($configItem) as $item => $server)
		{
			//LDAP variables
			$ldapurl = $server['url'];
			$ldapdn = $server['binddn'];
			$ldappw = $server['bindpw'];
			$version = $server['version'];
				
			$master ?  $key = 'master' : $key = 'slave';
			
			if($this->connect($ldapurl, $ldapdn, $ldappw, $version)) 
			{
				$this->servers[$key][$item] = $server;
				$this->servers[$key][$item]['connection'] = $this->connection;
				return true;
			} else {
				log_message('debug', 'The ldap server set in '.$configItem.'['.$item.'] cannot be connected.');
				return false;
			}
		}
	}
	
	/**
	 * Checks if there is at least one connection to a LDAP server
	 * 
	 * @access		private
	 * @param		none
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 * 
	 * @todo		
	 */
	private function checkNeededConnections()
	{
		if(isset($this->servers['master']) && count($this->servers['master']) == 0) 
		{
			if(isset($this->servers['slave']) && count($this->servers['slave']) == 0)	
			{
				$this->report('connection','I can not connect to any LDAP master or slave server.');
				return false;
			} else {
				$this->report('connection','No ldap master server found. Write operations will fail.');
			}
		}
		
		if(isset($this->servers['slave']) && count($this->servers['slave']) == 0)	
		{
			$this->report('connection','No ldap slave server found, master server will be used instead.');
		}
		
		return true;
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
	public function CEcreate($entry, $dn = null) {
		
		if(!$this->initialize()) return $this->restReturn($this->result);
		
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside 
		
		$this->connection = $this->WrConnection;
		
		return $this->restReturn($this->create($entry, $dn));
		
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
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Feb 24, 2012
	 *
	 * @todo	It's possible to perform a search on multiple DNs. http://www.php.net/manual/en/function.ldap-search.php#94554 This might be useful if I want to perform a search on both people and organizations in one shot
	 */	
	public function CEsearch($baseDn, $filter, $attributes = null, $attributesOnly = 0, $deref = null, array $sort_by = null, $flow_order = null, $wanted_page = null, $items_page = null) {
		
		if(!$this->initialize()) return $this->restReturn($this->result);
		
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside
		
		$this->connection = $this->RoConnection;
		
		return $this->restReturn($this->search($baseDn, $filter, $attributes, $attributesOnly, null, null, $deref, $sort_by, $flow_order, $wanted_page, $items_page));
	}

	
	/**
	 * The method read() is a sort of interface for the search() method and it's meant to return all the attributes of the current ldap object.
	 *
	 * @access		public
	 * @param		$dn				string	The DN of the new entry. If empty $this->dn will be used instead.
	 * @param		$sizeLimit		integer		Search param. It limits the max amount of items to get from LDAP. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$timeLimit		integer		Search param. It limits the max amount of time to perform the LDAP search query. If it's not set it will be substituted with the value stored in the config file.
	 * @param		$deref			integer		Search param. It specifies how aliases should be handled during the search. http://www.php.net/ldap_list
	 * @var
	 * @return		boolean
	 * @example
	 * @see
	 *
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 *
	 * @todo
	 */	
	public function CEread($dn = null, $sizeLimit = null, $timeLimit = null, $defer = null) {
	
		if(!$this->initialize()) return $this->restReturn($this->result);
	
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside
		
		$this->connection = $this->RoConnection;
	
		return $this->restReturn($this->read($dn, $sizeLimit, $timeLimit, $defer));
	}

	
	/**
	 * Updates a LDAP entry
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
	 * @since		Mar 4, 2012
	 *
	 * @todo
	 */	
	public function CEupdate($entry, $dn = null, $delete = false) {
		
		if(!$this->initialize()) return $this->restReturn($this->result);
		
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside
		
		$this->connection = $this->WrConnection;
		
		return $this->restReturn($this->update($entry, $dn, $delete));
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
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 *
	 * @todo
	 */
	public function CEdelete($dn = null) {
		
		if(!$this->initialize()) return $this->restReturn($this->result);
		
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside
		
		$this->connection = $this->WrConnection;
		
		return $this->restReturn($this->delete($dn));
	}
	
	
	public function CEdelete_attribute($entry, $dn = null) {
	
		if(!$this->initialize()) return $this->restReturn($this->result);
	
		$this->reset_result(); //cleaning $this->result from what initialize wrote inside
	
		$this->connection = $this->WrConnection;
	
		return $this->restReturn($this->mod_del($entry, $dn));
	}	
	
	/**
	 * Performs a sort of loadbalancing between the LDAP servers (slaves or masters) if more than one is provided
	 * 
	 * @access		private
	 * @param		none
	 * @var			
	 * @return		none
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 * 
	 * @todo		
	 */
	private function getActiveConnections() {
		
		$num_masters = 0;
		$num_slaves = 0;
		
		if(isset($this->servers['master'])) $num_masters = count($this->servers['master']);
		if(isset($this->servers['slave'])) $num_slaves = count($this->servers['slave']);

		if($num_masters == 0)
		{
			$this->WrConnection = null;
		}
				
		if($num_masters == 1)
		{
			$this->WrConnection = $this->servers['master']['0']['connection'];
		}
		
		if($num_masters > 1)
		{
			//connect randomly to one of the given master (provides a bit of load balancing)
			$serverId = array_rand($this->servers['master'], 1);
			$this->WrConnection = $this->servers['master'][$serverId]['connection'];
		}		
		
		if($num_slaves == 0)
		{
			$this->RoConnection = $this->WrConnection;
		}
		
		if($num_slaves == 1)
		{
			$this->RoConnection = $this->servers['slave']['0']['connection'];
		}		
		
		if($num_slaves > 1)
		{
			//connect randomly to one of the given slave (provides a bit of load balancing)
			$serverId = array_rand($this->servers['slave'], 1);
			$this->RoConnection = $this->servers['slave'][$serverId]['connection'];
		}				
	}
	
	/**
	 * Adds the https_status code (200) if the LDAP return is true and attaches data to the result.
	 * The result, in this way, is ready to be sent back to the REST client.
	 * 
	 * @access		private
	 * @param		$exit_status	boolean		It's meant to be the LDAP Object Class exit status.
	 * @var			
	 * @return		boolean
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.contact-engine.info
	 * @since		Mar 4, 2012
	 * 
	 * @todo		
	 */
	private function restReturn($exit_status) {
		if($exit_status){
			$this->data->http_status_code = '200';
			$this->result->storeData($this->data);
			return true;
		} else {
			$this->result->fillDataOnError();
		}
		return false;
	}	
}

/* End of ri_ldap.php */