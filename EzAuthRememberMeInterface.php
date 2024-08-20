<?php
namespace elmyrockers;






interface EzAuthRememberMeInterface {
	public function __construct( $config );
	public function generateToken( $user );
	public function verifyToken();
}