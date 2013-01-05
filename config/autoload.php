<?php

# Load the ri_ldap config when the spark is loaded
$autoload['config'] = array('ri_ldap');

# Load a ri_ldap library
$autoload['libraries'] = array('zend_ldap'); //zend library: useful to parse LDAP schemas

# Load the ldap and ri_ldap models when the spark is loaded
$autoload['model'] = array('ldap_error_object', 'ldap_data_object', 'ldap_return_object', 'ldap', 'ri_ldap');

# Load the ri_ldap helper when the spark is loaded
$autoload['helper'] = array('ri_ldap');
