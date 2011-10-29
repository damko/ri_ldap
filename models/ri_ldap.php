<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// Created on Sep 2, 2011 by dam 
// d.venturin@squadrainformatica.com

/**
 * 
 * Contact Engine ldap interface
 * @author damko
 *
 */
class Ri_Ldap extends Ldap {
	protected $servers = array();  //contains the configuration 
	protected $WrConnection;
	protected $RoConnection;
	
	public function __construct() {
		parent::__construct();
		
		//establish the connection to one of the Master servers
		log_message('debug', 'Establishing master LDAP servers connections');
		$this->establishLdapConnection('ldapMaster',true);		
		
		log_message('debug', 'Establishing slave LDAP servers connections');
		$this->establishLdapConnection('ldapSlave',false);		
		
		if($this->checkNeededConnections()) $this->getActiveConnections();
		
		log_message('debug', 'ri_ldap class has been loaded');
	}
	
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
	}
	
	public function getServers()
	{
		return $this->servers;
	}
	/**
	 * 
	 * 
	 * @param text $configItem 	Name of the configuration item: ldapMaster or ldapSlave
	 * @param boolean $master	Specifies if the connection to establish is meant to be a connection to a master server or a slave
	 */
	private function establishLdapConnection($configItem,$master){
		//check configuration
		if(!$this->config->item($configItem)) die('No configuration found with item "'.$configItem.'". Check your configuration file.');

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
			} else {
				log_message('debug', 'The ldap server set in '.$configItem.'['.$item.'] cannot be connected.');
			}
		}
	}
	
	private function checkNeededConnections()
	{
		if(count($this->servers['master']) == 0) 
		{
			if(count($this->servers['slave']) == 0)	
			{
				die ('I can not connect to any LDAP master or slave server');	
			} else {
				log_message('debug', 'No ldap master server found. Write operations will fail.');
			}
		}
		
		if(count($this->servers['slave']) == 0)	
		{
			log_message('debug', 'No ldap slave server found, master server will be used instead.');
		}
		
		return true;
	}
	
	public function CEcreate($dn, array $entry) {
		$this->connection = $this->WrConnection;
		return $this->create($dn, $entry);
	}
	
	public function CEsearch($baseDn, $filter, array $attributes, $attributesOnly = 0, $deref = null, array $sort_by = null, $flow_order = null, $wanted_page = null, $items_page = null) {
		$this->connection = $this->RoConnection;
		return $this->search($baseDn, $filter, $attributes, $attributesOnly, null, null, $deref, $sort_by, $flow_order, $wanted_page, $items_page);
	}
	
	public function CEupdate($dn, array $entry) {
		$this->connection = $this->WrConnection;
		return $this->update($dn, $entry);
	}
	
	public function CEdelete($dn) {
		$this->connection = $this->WrConnection;
		return $this->delete($dn);
	}
				
	private function getActiveConnections() {
		$num_masters = count($this->servers['master']);
		$num_slaves = count($this->servers['slave']);

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
}

/* End of ri_ldap.php */