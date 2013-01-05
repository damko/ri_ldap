<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 * Contact Engine configuration file
 * Created on  by dam d.venturin@squadrainformatica.com
 */
/**
 * This file contains the configuration for the LDAP connections established by the Spark ri_ldap
 * You can specify more than one master or slave server to get high-availability.
 * If you have only one LDAP server just set the same values for master[0] and slave[0]  
 * If you prefer, you can store this config file in the classic position APPATH.'config/ldap.php'
 * and those settings will be taken instead of the ones below.
 * 
 * @author 		Damiano Venturin
 * @since		Aug 11, 2011	
 */
 
if(file_exists(APPPATH.'/config/ldap.php')) {
	
	include(APPPATH.'/config/ldap.php');
	
} else {

	//Parameters influencing search results. Ref: http://it.php.net/manual/en/function.ldap-search.php
	$config['ldap']['sizeLimit'] = '10000'; //Enables you to limit the count of entries fetched. Setting this to 0 means no limit. 
	$config['ldap']['timeLimit'] = '7'; //Sets the number of seconds how long is spend on the search. Setting this to 0 means no limit.
	$config['ldap']['defer'] = '0'; //Specifies how aliases should be handled during the search.
	$config['ldap']['debug'] = false;  //outputs in $ldap->result all the PHP errors even the ones different from OutOfRangeException (errno 8)
	$config['ldap']['service_unavailable'] = false;  //if true all the requestes are dropped and an error message is sent back (503 HTTP error)
	
	//1ST LDAP MASTER SERVER
	$config['ldapMaster'][0]['url'] = "ldap://localhost:389";
	$config['ldapMaster'][0]['version'] = 3;
	$config['ldapMaster'][0]['binddn'] = "cn=admin,dc=example,dc=com";
	$config['ldapMaster'][0]['bindpw'] = "password";
	
	//1ST LDAP SLAVE SERVER
	$config['ldapSlave'][0]['url'] = "ldap://localhost:389";
	$config['ldapSlave'][0]['version'] = 3;
	$config['ldapSlave'][0]['binddn'] = "cn=admin,dc=example,dc=com";
	$config['ldapSlave'][0]['bindpw'] = "password";
	
	//2ND LDAP SLAVE SERVER
	// $config['ldapSlave'][1]['url'] = "ldap://ldapslave1:389";
	// $config['ldapSlave'][1]['version'] = 3;
	// $config['ldapSlave'][1]['binddn'] = "cn=admin,dc=example,dc=com";
	// $config['ldapSlave'][1]['bindpw'] = "password";	
}