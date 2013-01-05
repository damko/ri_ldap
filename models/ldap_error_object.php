<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Ldap Error Object. Contains a typical PHP error or any other exception occurred in the Ldap / RiLdap methods.
 * Its attributes are protected and can not be set to null.
 * 
 * @author 		Damiano Venturin
 * @copyright 	2V S.r.l.
 * @license		GPL
 * @link		http://www.contact-engine.info
 * @since		Feb 16, 2012
 * 
 * @todo
 */
class Ldap_Error_Object extends CI_Model {
	protected $http_status_code; //this is for REST response.
	protected $http_status_message; //this is for REST response.
	protected $message;
	protected $php_errno;
	protected $php_errtype;
	protected $file;
	protected $line;
	
	public function __construct(){
		parent::__construct();
	}
	
	public function __destruct(){
	
	}
	
	public function __set($attribute, $value) {
		if(!is_null($value) && !is_array($value)) $this->$attribute = $value;
	}
	
	public function __get($attribute) {
		return isset($this->$attribute) ? $this->$attribute : null;
	}

	public function __isset($attribute) {
		return isset($this->$attribute) ? true : false;
	}
}