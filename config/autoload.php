<?php

# Load the ri_ldap config when the spark is loaded
$autoload['config'] = array('ri_ldap');

# Load a ri_ldap library
$autoload['libraries'] = array('zend_ldap'); //zend library: useful to parse LDAP schemas

# Load the ri_ldap helper when the spark is loaded
$autoload['model'] = array('ldap','ri_ldap');