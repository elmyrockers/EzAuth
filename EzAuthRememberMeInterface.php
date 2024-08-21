<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function initialize( $config );
	public function generateToken( $user );
	public function verifyToken();
}