<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function initialize( $config );
	public function generateToken(\RedBeanPHP\OODBBean $user ):bool;
	public function verifyToken():bool|\RedBeanPHP\OODBBean;
}