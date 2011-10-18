<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// Created on Sep 2, 2011 by dam 
// d.venturin@squadrainformatica.com

class Ldap extends CI_Model {
	protected $connected;
	public $connection;
	protected $connection_error;
	protected $results_number;
	protected $results_got_number;
	protected $results_pages;
	protected $results_page;
	
	public function __construct() {
		
		parent::__construct();
		
		//loading the configuration about the LDAP servers
		//$this->load->config('ldap');
		
		log_message('debug', 'Ldap class has been loaded');
	}
	
	public function __destruct()
	{
		$this->disconnect();	
	}
	
	/**
	 * 
	 * Connects to a given ldap server
	 * 	useful infos:
	 *	openldap error table:http://www.zytrax.com/books/ldap/ch12/
	 *	about referral: http://www.zytrax.com/books/ldap/ch11/referrals.html
	 *	about referral: http://www.zytrax.com/books/ldap/ch7/referrals.html
	 *
	 * @param text $ldapurl	
	 * @param text $ldapdn
	 * @param text $ldappw
	 * @return boolean
	 */
	public function connect($ldapurl,$ldapdn,$ldappw,$version = '3') {
		
		// Connecting to LDAP
		$this->connection = ldap_connect($ldapurl);
		if(!$this->connection)
		{
			$this->connected = false;
			$this->connection_error = 'Can not connect to the given LDAP server '.$ldapurl.' Check the LDAP url';
			log_message('debug', $this->connection_error);
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
			$this->connection_error = 'Can not bind to the given LDAP server '.$ldapurl.' Check the LDAP url and credentials';
			log_message('debug', $this->connection_error);
			return false;
		}
	}	
	
	/**
	 * 
	 * Connects another LDAP server as specified in the referral if the given server can not perform the request.
	 * THIS FUNCTION IS INTENTIONALLY COMMENTED and NOT USED because there is a big deal with referral. If the server
	 * is down, there is no referral so it's much better to try to connect to another server specified in the config
	 * file. So this method is here just for documentation purpose in case of need.
	 * @param unknown_type $connection
	 * @param unknown_type $referral
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
	
	public function disconnect() {
		if(is_resource($this->connection)) ldap_unbind($this->connection);
	}
	
	private function valideEntry() {
		return true;
	}
	
	public function search($baseDN, $filter,array $attributes, $attributesOnly = 0, $sizeLimit = null, $timeLimit = null, $deref = null, array $sort_by = null, $flow_order = null, $wanted_page = null, $items_page = null) {
		//TODO Note: it's possible to perform a search on multiple DNs. http://www.php.net/manual/en/function.ldap-search.php#94554 This might be useful if I want to perform a search on both
		//people and organizations in one shot
		
		//validation
		if(!$this->connection) return false;
		if(!$baseDN) return false;
		if(!$filter) return false;
		$attrs = array();
		if(!empty($attributes)) $attrs = $attributes;
		if(is_null($sizeLimit)) $sizeLimit = $this->config->item('sizeLimit');
		if(is_null($timeLimit)) $timeLimit = $this->config->item('timeLimit');
		if(is_null($deref)) $deref = $this->config->item('defer');

		//$result = ldap_search($this->connection, $baseDN, $filter, $attrs, $attributesOnly, $sizeLimit, $timeLimit, $deref) or die ("Error in search LDAP query");
		
		//performing the search
		//I avoided to use ldap_search because when the results number is > 1 the "dn" is always returned irrespectively of which attributes types are requested
		//and this gives problems when sorting (the first item goes somewhere else in the array and it's hard to remove it)
		$result = ldap_list($this->connection, $baseDN, $filter, $attrs, $attributesOnly, $sizeLimit, $timeLimit, $deref) or die ("Error in search LDAP query");
		
		//count entries
		$this->results_number = ldap_count_entries( $this->connection, $result ); //when the result is > 1 the first entry is always the DN. 
		
		//retrieve entries: sort and paginate results if necessary
		$data = $this->sort_paginate($result, $sort_by, $flow_order, $wanted_page, $items_page);
				
		return $this->add_info($data);
	}
	
	private function add_info($data)
	{
		//adding info about the query results
		//TODO to be really restfull I should also pass the url to get the next page
		if(count($data) >= 1)
		{
			$data['RestStatus'] = array(
										'results_number' => $this->results_number,
										'results_got_number' => $this->results_got_number,
										'results_pages' => $this->results_pages,
										'results_page' => $this->results_page,												
			);
		}
		return $data;
	}
	
	/**
	* Order a search in ascending and descending order.
	*
	* @param resource from ldap_connect()
	* @param resource from ldap_search()
	* @param string of attribute to order
	* @param string "asc" or "desc"
	* @param integer page number, first is 0 zero
	* @param integer entries per page
	* @return string[]
	*/
	private function sort_paginate($result, array $sFields, $sOrder = "asc", $iPage = null, $iPerPage = null )
	{				
		if(is_null($sFields)) $sFields = array();
		
		if ( $iPage === null || $iPerPage === null )
		{
			# fetch all in one page
			$iStart = 0;
			$iEnd = $this->results_number - 1;
		}
		else
		{
			# calculate range of page
			$iStart = $iPerPage * $iPage;
			$iEnd = $iStart + $iPerPage - 1;
			if ( $sOrder === "desc" )
			{
				# revert range
				$iStart = $this->results_number - 1 - $iEnd;
				$iEnd = $iStart + $iPerPage - 1;
			}
		}
		
		# fetch entries
		foreach ($sFields as $sField) {
			ldap_sort( $this->connection, $result, $sField );
		}
		
		$data = array();
		for (
	        	$iCurrent = 0, $rEntry = ldap_first_entry( $this->connection, $result );
				$iCurrent <= $iEnd && is_resource( $rEntry );
				$iCurrent++, $rEntry = ldap_next_entry( $this->connection, $rEntry )
			) {
			if ( $iCurrent >= $iStart )
			{
				array_push( $data, ldap_get_attributes( $this->connection, $rEntry ) );
			}
		}
		
		//adding RESTinfo
		$this->results_got_number = count($data);
		
		$iPerPage == 0 ? $this->results_pages = 1 : $this->results_pages =  ceil( $this->results_number / $iPerPage );
		
		if($this->results_got_number == $this->results_number) 
		{
			$this->results_page = 1;
		} else {
			$this->results_page = $iPage;
		}
		
		# if order is desc revert page's entries
		return $sOrder === "desc" ? array_reverse( $data ) : $data;
	}	
	
	public function create($dn, array $entry) {
		//validation
		if(!$this->connection) return false;
		if(empty($dn) or is_array($dn)) return false;
		if(empty($entry)) return false;
		if(!$this->valideEntry($entry)) return false;

		//return $data;
		return ldap_add($this->connection,$dn,$entry);		
	}
	
	public function update($dn, array $entry) {
		//validation
		if(!$this->connection) return false;
		if(empty($dn) or is_array($dn)) return false;
		if(empty($entry)) return false;
		if(!$this->valideEntry($entry)) return false;

		return ldap_modify($this->connection,$dn,$entry);
	}
	
	public function delete($dn)
	{
		if(empty($dn) or is_array($dn)) return false;
		return ldap_delete($this->connection, $dn);
	}
}


/* End of ldap.php */