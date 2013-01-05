<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Ldap Data Object. Contains the LDAP data result plus some information useful for pagination and the http_status_code for REST.
 * Its attributes are protected and can not be set to null. 
 * 
 * @author 		Damiano Venturin
 * @copyright 	2V S.r.l.
 * @license		GPL
 * @link		http://http://www.contact-engine.info
 * @since		Feb 16, 2012
 * 
 * @todo
 */
class Ldap_Data_Object extends CI_Model {
	protected $http_status_code; //this is for REST response.
	protected $http_status_message; //this is for REST response.
	protected $content = array();
	protected $results_number; //total number of result for the current request
	protected $results_pages; //number of pages necessary to display all the data
	protected $results_page; //page number of the current set	
	protected $results_got_number; //number of items contained in the current set
		
	public function __construct(){
		parent::__construct();
	}
	
	public function __destruct(){
	
	}
	
	public function __set($attribute, $value) {
		
		//"http_status_message" is set automatically when http_status_code is set
		if($attribute == 'http_status_message') return;
		
		if(!is_null($value) && !is_array($value) && $attribute != 'content') $this->$attribute = $value;
		
		//"content" is the only protected attribute which might be an array
		if(is_array($value) && $attribute == 'content') $this->$attribute = $value;
		
		if($attribute == 'http_status_code') { 
		
			$http_status_codes = get_HTTP_status_codes();
		
			$this->http_status_code = $value;
			
 			if(!in_array($this->http_status_code, array_keys($http_status_codes['all'])))
 			{
 				$this->http_status_code = '500';  //internal server error
 			}
		
			$this->http_status_message = $http_status_codes['all'][$this->http_status_code];
		}		
	}
	
	public function __get($attribute) {
		return isset($this->$attribute) ? $this->$attribute : null;
	}
	
	public function __isset($attribute) {
		return isset($this->$attribute) ? true : false;
	}
}