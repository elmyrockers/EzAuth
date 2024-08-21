<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function initialize(array $config );
	public function generateToken(\RedBeanPHP\OODBBean $user ):bool;
	public function verifyToken():bool|\RedBeanPHP\OODBBean;
}