<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ldap_Return_Object extends CI_Model {
	var $errors = array(); //contains the collected errors
	var $data; //contains data retrieved from LDAP
	
	public function __construct(){
		parent::__construct();
	}
		
	public function __destruct(){
		
	}
		
	public function __set($attribute, $value) {
		$this->$attribute = $value;
	}
		
	public function __get($attribute) {
		return $this->$attribute;
	}
	
	public function addError($type, $message, $file, $line, $http_status_code) {
		//$error = new Ldap_Error_Object();
		$error = $this->setError($type, $message, $file, $line, $http_status_code);
		$this->storeError($error);
	}
	
	/**
	* This function populates the Ldap_Error_Object attributes and grants to have uniform Ldap_Error_Objects
	* with all the attributes filled.
	*
	* @access		public
	* @param		$php_errno			integer		PHP error number
	* @param		$message			string		The error message
	* @param		$file				string		Path of the file generating the error
	* @param		$line				integer		Line of code generating the error
	* @param		$http_status_code	integer		The http status code returned to REST
	* @var
	* @return		nothing
	* @example
	* @see
	*
	* @author 		Damiano Venturin
	* @copyright 	2V S.r.l.
	* @license		GPL
	\* @link		http://www.contact-engine.info
	* @since		Feb 23, 2012
	*
	* @todo
	*/
	public function setError($php_errno, $message, $file, $line, $http_status_code) {
	 
		$error = new Ldap_Error_Object();
		
		$http_status_codes = get_HTTP_status_codes();
		$php_error_codes = get_PHP_error_codes();
	
		if(!in_array($http_status_code, array_keys($http_status_codes['all_errors'])))
		{
			$error->http_status_code = '500';
		} else {
			$error->http_status_code = $http_status_code;
		}
	
		$error->http_status_message = $http_status_codes['all_errors'][$error->http_status_code];
	
		if(!in_array($php_errno,array_keys($php_error_codes)))
		{
			$error->php_errno = '8';
		} else {
			$error->php_errno = $php_errno;
		}
			
		$error->php_errtype = $php_error_codes[$php_errno];
		$error->message = $message;
		$error->file = $file;
		$error->line = $line;
		
		return $error;
	}

	/**
	 * Adds an error to the errors array ($this->errors)
	 * 
	 * @access		public
	 * @param		$error		Ldap_Error_Object	The error
	 * @var			
	 * @return		nothing
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.squadrainformatica.com/en/development#mcbsb  MCB-SB official page
	 * @since		Feb 23, 2012
	 * 
	 * @todo		
	 */
	private function storeError(Ldap_Error_Object $error) {
		array_push($this->errors, $error);
	}

 	
	/**
	 * Transfers the data collected in the $ldap->data (temporary) Ldap_Data_Object in $this->data, performing
	 * some checks to grant a uniform and qualitative Ldap_Data_Object in return 
	 * 
	 * @access		public
	 * @param		$data	Ldap_Data_Object
	 * @var			
	 * @return		
	 * @example
	 * @see
	 * 
	 * @author 		Damiano Venturin
	 * @copyright 	2V S.r.l.
	 * @license		GPL
	 * @link		http://www.squadrainformatica.com/en/development#mcbsb  MCB-SB official page
	 * @since		Feb 23, 2012
	 * 
	 * @todo		
	 */
	public function storeData(Ldap_Data_Object $data) {
		//TODO should I add an error?
		if(empty($data->http_status_code)) return;
		if(!isset($data->content)) return;
		
		$this->data = $data;
		
		//TODO I am not sure about this
		if(!isset($this->data->results_number)) $this->data->results_number = count($this->data->content);
 		if(!isset($this->data->results_pages)) $this->data->results_pages = '0';
 		if(!isset($this->data->results_page)) $this->data->results_page = '0';
		if(!isset($this->data->results_got_number)) $this->data->results_got_number = $this->data->results_number;
	}

	/**
	 * This method is called whenever a RiLdap method performs one of the LDAP class methods. If the exit status is false
	 * it means that there are errors thrown and then the result contained by the LDAP Data Object should report the most
	 * meaningful information provided by the errors
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
	 * @link		http://www.contact-engine.info
	 * @since		Mar 7, 2012
	 * 
	 * @todo		
	 */
	public function fillDataOnError()
	{
		$errors = $this->errors;
		
		//Takes the last error because it's generally the most meaningful (thrown with error type OutOfRangeException)
		$error = array_pop($errors);
		
		$this->data = new Ldap_Data_Object();
		$this->data->http_status_code = $error->http_status_code;
		$this->data->http_status_message = $error->http_status_message;
		$this->data->content = array();
		$this->data->results_number = '0';
		$this->data->results_pages = '0';
		$this->data->results_page = '0';
		$this->data->results_got_number = '0';
	}
}
